<?php
// web/dtr.php
session_start();

// ==============================================
// 1. FIX PATHS - config.php is in DB_Conn folder at root level
// ==============================================
require_once __DIR__ . '/../DB_Conn/config.php';

// ==============================================
// 2. CHECK LOGIN STATUS
// ==============================================
function isLoggedIn()
{
    return isset($_SESSION['user_role']) &&
        isset($_SESSION['user_id']) &&
        isset($_SESSION['acc_number']);
}

// Redirect to login if not logged in
if (!isLoggedIn()) {
    $_SESSION['login_error'] = 'Please login first to access the shop.';
    header('Location: ../login.php');
    exit;
}

// ==============================================
// 3. GET USER DATA FROM SESSION
// ==============================================
$userRole = $_SESSION['user_role'];
$userId = $_SESSION['user_id'];
$accNumber = $_SESSION['acc_number'];

// Fetch user details from database
$userData = null;
if ($userRole === 'Admin') {
    $stmt = $pdo->prepare("SELECT id, acc_number, f_name, email, phone_number, role, user_name, authorize_access FROM admins WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($userRole === 'Customer') {
    $stmt = $pdo->prepare("SELECT id, acc_number, f_name, email, phone_number FROM customers WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$userData) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

$user = $userData;

// Set timezone
date_default_timezone_set('Asia/Manila');
$currentDateTime = date('D, j M Y g:i A');

// Daily login bonus / update last login date
$storedDate = $user['last_login_date'] ?? '';
if ($storedDate !== $currentDateTime) {
    $updateStmt = $pdo->prepare("UPDATE admins SET last_login_date = ? WHERE acc_number = ?");
    $updateStmt->execute([$currentDateTime, $_SESSION['acc_number']]);

    // Refresh user data
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE acc_number = ?");
    $stmt->execute([$_SESSION['acc_number']]);
    $user = $stmt->fetch();
}

// Update status to online (1 = online, 0 = offline)
$stmt = $pdo->prepare("UPDATE admins SET status = 1 WHERE acc_number = ?");
$stmt->execute([$_SESSION['acc_number']]);

// ==============================================
// 4. FETCH ALL DTR RECORDS FROM DTR TABLE ONLY
// ==============================================
$stmt = $pdo->prepare("SELECT * FROM dtr ORDER BY date DESC, time_in DESC");
$stmt->execute();
$dtrRecords = $stmt->fetchAll();

// ==============================================
// FUNCTION TO DETERMINE ONLINE STATUS
// ==============================================
function getOnlineStatus($onlineTime)
{
    if (empty($onlineTime)) {
        return ['status' => 'offline', 'class' => 'status-offline', 'text' => '● Offline', 'time_diff' => ''];
    }

    $storedTimestamp = strtotime($onlineTime);
    if ($storedTimestamp === false) {
        return ['status' => 'offline', 'class' => 'status-offline', 'text' => '● Offline', 'time_diff' => ''];
    }

    $currentTimestamp = time();
    $diffSeconds = $currentTimestamp - $storedTimestamp;
    $diffMinutes = floor($diffSeconds / 60);
    $diffHours = floor($diffSeconds / 3600);
    $diffDays = floor($diffSeconds / 86400);
    $diffWeeks = floor($diffSeconds / 604800);

    if ($diffMinutes <= 1) {
        return ['status' => 'online', 'class' => 'status-online', 'text' => '● Online', 'time_diff' => ''];
    } elseif ($diffMinutes >= 1 && $diffMinutes <= 60) {
        return ['status' => 'away', 'class' => 'status-away', 'text' => '● Away', 'time_diff' => $diffMinutes . 'm'];
    } elseif ($diffHours >= 1 && $diffHours < 24) {
        return ['status' => 'offline', 'class' => 'status-offline', 'text' => '● Offline', 'time_diff' => $diffHours . 'h'];
    } elseif ($diffDays >= 1 && $diffDays < 7) {
        return ['status' => 'offline', 'class' => 'status-offline', 'text' => '● Offline', 'time_diff' => $diffDays . 'd'];
    } elseif ($diffWeeks >= 1 && $diffWeeks < 4) {
        return ['status' => 'offline', 'class' => 'status-offline', 'text' => '● Offline', 'time_diff' => $diffWeeks . 'w'];
    } else {
        return ['status' => 'offline', 'class' => 'status-offline', 'text' => '● Offline', 'time_diff' => '4w+'];
    }
}

// ==============================================
// FUNCTION TO GET UNREAD COUNT
// ==============================================
function getUnreadCount($pdo, $accNumber)
{
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM messages WHERE receiver_acc = ? AND is_read = 0");
        $stmt->execute([$accNumber]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <meta http-equiv="refresh" content="30">
    <title>DTR | Villaruz Print Shop & General Merchandise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: #f1f5f9;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .app-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .main-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: #ffffff;
            padding: 20px 30px;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .welcome h1 {
            font-size: 28px;
            font-weight: 700;
            color: #0f172a;
        }

        .welcome h4 {
            font-size: 20px;
            font-weight: 600;
            color: #0f172a;
        }

        .burger-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            width: 48px;
            height: 48px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1001;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
        }

        .burger-btn:hover {
            background: #f8fafc;
            transform: scale(1.02);
        }

        .burger-btn i {
            font-size: 24px;
            color: #3b82f6;
        }

        .side-menu {
            position: fixed;
            top: 0;
            right: -320px;
            width: 280px;
            height: 100vh;
            background: #ffffff;
            box-shadow: -5px 0 25px rgba(0, 0, 0, 0.1);
            z-index: 1002;
            transition: right 0.3s ease;
            display: flex;
            flex-direction: column;
            border-left: 1px solid #e2e8f0;
        }

        .side-menu.open {
            right: 0;
        }

        .menu-header {
            padding: 25px 20px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .menu-header .user-name {
            font-weight: 700;
            font-size: 18px;
            color: #0f172a;
            margin-top: 8px;
        }

        .menu-header .user-greeting {
            font-size: 13px;
            color: #64748b;
        }

        .menu-header i {
            font-size: 40px;
            color: #3b82f6;
        }

        .menu-nav {
            flex: 1;
            padding: 20px;
        }

        .menu-nav .nav-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 14px 12px;
            border-radius: 14px;
            color: #475569;
            text-decoration: none;
            transition: all 0.2s;
            margin-bottom: 8px;
        }

        .menu-nav .nav-item i {
            width: 24px;
            font-size: 20px;
            color: #3b82f6;
        }

        .menu-nav .nav-item span {
            font-size: 15px;
            font-weight: 500;
        }

        .menu-nav .nav-item:hover {
            background: #eff6ff;
            color: #1e293b;
        }

        .menu-nav .nav-item.active {
            background: #eff6ff;
            color: #3b82f6;
            border-left: 3px solid #3b82f6;
        }

        .menu-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(2px);
            z-index: 1000;
            display: none;
        }

        .menu-overlay.active {
            display: block;
        }

        .merchandise-section {
            background: #ffffff;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            overflow-x: auto;
            margin-top: 30px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .section-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .section-header h5 {
            font-size: 22px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #0f172a;
        }

        .inventory-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            white-space: nowrap;
        }

        .inventory-table th,
        .inventory-table td {
            padding: 15px 12px;
            text-align: center;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .inventory-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            position: sticky;
            top: 0;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .inventory-table tr:hover {
            background: #f8fafc;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-badge.present {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge.late {
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge.absent {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-badge.half_day {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-badge.default {
            background: #f1f5f9;
            color: #475569;
        }

        /* Photo Styles */
        .photo-thumb {
            width: 60px;
            height: 60px;
            object-fit: cover;
            cursor: pointer;
            border-radius: 5px;
            transition: transform 0.2s;
            border: 2px solid #e2e8f0;
        }

        .photo-thumb:hover {
            transform: scale(1.1);
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
            border-color: #3b82f6;
        }

        .photo-thumb.time-in-photo {
            border-color: #3b82f6;
        }

        .photo-thumb.time-out-photo {
            border-color: #ef4444;
        }

        .no-photo {
            color: #94a3b8;
            font-style: italic;
            font-size: 12px;
        }

        .dtr-date {
            font-weight: 500;
            color: #0f172a;
        }

        .dtr-time {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #1e293b;
        }

        .dtr-time.empty {
            color: #94a3b8;
        }

        .customer-acc {
            font-size: 13px;
            color: #0f172a;
            font-weight: 500;
        }

        /* Photo Modal */
        .photo-modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.92);
            overflow: hidden;
            cursor: zoom-out;
        }

        .photo-modal.active {
            display: block;
        }

        .photo-modal-content {
            position: relative;
            margin: auto;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 100%;
            padding: 20px;
        }

        .photo-modal-image {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
            transition: transform 0.3s ease;
            cursor: zoom-in;
            border-radius: 5px;
        }

        .photo-modal-image.zoomed {
            transform: scale(2);
            cursor: zoom-out;
        }

        .photo-modal-close {
            position: fixed;
            top: 20px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            transition: 0.3s;
            cursor: pointer;
            z-index: 10000;
            background: rgba(0, 0, 0, 0.6);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid rgba(255, 255, 255, 0.2);
            color: white;
        }

        .photo-modal-close:hover {
            color: #fff;
            background: rgba(255, 0, 0, 0.3);
            transform: scale(1.1);
            border-color: #ef4444;
        }

        .photo-modal-caption {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            color: white;
            font-size: 16px;
            background: rgba(0, 0, 0, 0.7);
            padding: 12px 24px;
            border-radius: 8px;
            text-align: center;
            max-width: 80%;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .zoom-controls {
            position: fixed;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 12px;
            z-index: 10000;
        }

        .zoom-controls button {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            backdrop-filter: blur(5px);
        }

        .zoom-controls button:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.05);
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }

            .inventory-table th,
            .inventory-table td {
                padding: 10px 8px;
                font-size: 12px;
            }

            .burger-btn {
                top: 15px;
                right: 15px;
                width: 42px;
                height: 42px;
            }

            .side-menu {
                width: 260px;
            }

            .status-badge {
                font-size: 10px;
                padding: 2px 8px;
            }

            .dtr-time {
                font-size: 11px;
            }

            .photo-thumb {
                width: 40px;
                height: 40px;
            }

            .photo-modal-caption {
                font-size: 14px;
                padding: 10px 16px;
                bottom: 20px;
            }

            .zoom-controls {
                bottom: 80px;
                gap: 8px;
            }

            .zoom-controls button {
                padding: 8px 14px;
                font-size: 12px;
            }
        }

        @media (max-width: 480px) {
            .inventory-table th,
            .inventory-table td {
                padding: 6px 4px;
                font-size: 10px;
            }

            .status-badge {
                font-size: 8px;
                padding: 1px 6px;
            }

            .dtr-time {
                font-size: 10px;
            }

            .photo-thumb {
                width: 30px;
                height: 30px;
            }

            .photo-modal-close {
                top: 10px;
                right: 15px;
                width: 40px;
                height: 40px;
                font-size: 30px;
            }

            .photo-modal-caption {
                font-size: 12px;
                padding: 8px 12px;
                bottom: 15px;
            }

            .zoom-controls {
                bottom: 60px;
                gap: 6px;
                flex-wrap: wrap;
                justify-content: center;
            }

            .zoom-controls button {
                padding: 6px 12px;
                font-size: 11px;
            }
        }
    </style>
</head>

<body>
    <div class="app-wrapper">
        <div class="burger-btn" id="burgerBtn">
            <i class="fas fa-bars"></i>
        </div>

        <div class="menu-overlay" id="menuOverlay"></div>

        <?php
        if ($user['authorize_access'] == 0) {
            include 'system_sidebar.php';
        } elseif ($user['authorize_access'] == 1) {
            include 'owner_sidebar.php';
        } elseif ($user['authorize_access'] == 2) {
            include 'admin_sidebar.php';
        }
        ?>

        <main class="main-content">
            <div class="dashboard-header">
                <div class="welcome">
                    <h4><i class="fas fa-clock"></i> Daily Time Records</h4>
                </div>
                <div>
                    <span style="font-size: 14px; color: #64748b;">
                        <i class="fas fa-calendar-alt"></i> <?php echo date('F j, Y'); ?>
                    </span>
                    <span style="font-size: 14px; color: #64748b; margin-left: 15px;">
                        <i class="fas fa-database"></i> <?php echo count($dtrRecords); ?> records
                    </span>
                </div>
            </div>

            <div class="merchandise-section">
                <div style="overflow-x: auto;">
                    <table class="inventory-table" id="dtrTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Account</th>
                                <th>User ID</th>
                                <th>Date</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                                <th>Status</th>
                                <th>In Photo</th>
                                <th>Out Photo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($dtrRecords)): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 40px; color: #94a3b8;">
                                        <i class="fas fa-clock" style="font-size: 40px; display: block; margin-bottom: 10px; color: #cbd5e1;"></i>
                                        No DTR records found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($dtrRecords as $record): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($record['id']); ?></td>
                                        <td>
                                            <span class="customer-acc"><?php echo htmlspecialchars($record['acc_number'] ?? ''); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($record['user_id']); ?></td>
                                        <td class="dtr-date">
                                            <?php echo date('M j, Y', strtotime($record['date'])); ?>
                                        </td>
                                        <td>
                                            <?php if ($record['time_in']): ?>
                                                <span class="dtr-time"><?php echo date('h:i A', strtotime($record['time_in'])); ?></span>
                                            <?php else: ?>
                                                <span class="dtr-time empty">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($record['time_out']): ?>
                                                <span class="dtr-time"><?php echo date('h:i A', strtotime($record['time_out'])); ?></span>
                                            <?php else: ?>
                                                <span class="dtr-time empty">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = 'default';
                                            $statusText = 'Present';
                                            if ($record['status'] == 'late') {
                                                $statusClass = 'late';
                                                $statusText = 'Late';
                                            } elseif ($record['status'] == 'absent') {
                                                $statusClass = 'absent';
                                                $statusText = 'Absent';
                                            } elseif ($record['status'] == 'half_day') {
                                                $statusClass = 'half_day';
                                                $statusText = 'Half Day';
                                            }
                                            ?>
                                            <span class="status-badge <?php echo $statusClass; ?>">
                                                <?php echo $statusText; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $timeInPhoto = $record['time_in_photo'] ?? '';
                                            if (!empty($timeInPhoto)): 
                                                $photoPath = '../DTR_Photos/' . $timeInPhoto;
                                            ?>
                                                <img src="<?php echo htmlspecialchars($photoPath); ?>" 
                                                     alt="Time In Photo" 
                                                     class="photo-thumb time-in-photo"
                                                     onclick="openPhotoModal('../DTR_Photos/<?php echo htmlspecialchars($photoPath); ?>', 'Time In - <?php echo htmlspecialchars($record['acc_number']); ?> - <?php echo date('M j, Y', strtotime($record['date'])); ?>')"
                                                     title="Click to view full size">
                                            <?php else: ?>
                                                <span class="no-photo">No photo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $timeOutPhoto = $record['time_out_photo'] ?? '';
                                            if (!empty($timeOutPhoto)): 
                                                $photoPath = '../DTR_Photos/' . $timeOutPhoto;
                                            ?>
                                                <img src="<?php echo htmlspecialchars($photoPath); ?>" 
                                                     alt="Time Out Photo" 
                                                     class="photo-thumb time-out-photo"
                                                     onclick="openPhotoModal('../DTR_Photos/<?php echo htmlspecialchars($photoPath); ?>', 'Time Out - <?php echo htmlspecialchars($record['acc_number']); ?> - <?php echo date('M j, Y', strtotime($record['date'])); ?>')"
                                                     title="Click to view full size">
                                            <?php else: ?>
                                                <span class="no-photo">No photo</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Photo Modal -->
    <div id="photoModal" class="photo-modal">
        <button class="photo-modal-close" onclick="closePhotoModal()">&times;</button>
        <div class="photo-modal-content">
            <img id="photoModalImage" class="photo-modal-image" src="" alt="DTR Photo">
        </div>
        <div id="photoModalCaption" class="photo-modal-caption"></div>
        <div class="zoom-controls">
            <button onclick="zoomIn()">🔍 Zoom In</button>
            <button onclick="zoomOut()">🔍 Zoom Out</button>
            <button onclick="resetZoom()">↺ Reset</button>
        </div>
    </div>

    <script>
        // ============================================================
        // SIDE MENU
        // ============================================================
        const burgerBtn = document.getElementById('burgerBtn');
        const sideMenu = document.getElementById('sideMenu');
        const menuOverlay = document.getElementById('menuOverlay');

        function toggleMenu() {
            sideMenu.classList.toggle('open');
            menuOverlay.classList.toggle('active');
        }

        function closeMenu() {
            sideMenu.classList.remove('open');
            menuOverlay.classList.remove('active');
        }

        burgerBtn.addEventListener('click', toggleMenu);
        menuOverlay.addEventListener('click', closeMenu);

        // Close menu on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeMenu();
        });

        // ============================================================
        // PHOTO MODAL
        // ============================================================
        let currentZoom = 1;

        function openPhotoModal(imageSrc, captionText) {
            const modal = document.getElementById('photoModal');
            const modalImage = document.getElementById('photoModalImage');
            const caption = document.getElementById('photoModalCaption');

            modalImage.src = imageSrc;
            modalImage.className = 'photo-modal-image';
            currentZoom = 1;
            caption.textContent = captionText || 'DTR Photo';
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closePhotoModal() {
            const modal = document.getElementById('photoModal');
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
            const modalImage = document.getElementById('photoModalImage');
            modalImage.className = 'photo-modal-image';
            currentZoom = 1;
        }

        function zoomIn() {
            const modalImage = document.getElementById('photoModalImage');
            currentZoom = Math.min(currentZoom + 0.3, 3);
            modalImage.style.transform = 'scale(' + currentZoom + ')';
            modalImage.style.cursor = 'zoom-out';
        }

        function zoomOut() {
            const modalImage = document.getElementById('photoModalImage');
            currentZoom = Math.max(currentZoom - 0.3, 0.5);
            modalImage.style.transform = 'scale(' + currentZoom + ')';
            if (currentZoom <= 1) {
                modalImage.style.cursor = 'zoom-in';
            }
        }

        function resetZoom() {
            const modalImage = document.getElementById('photoModalImage');
            currentZoom = 1;
            modalImage.style.transform = 'scale(1)';
            modalImage.className = 'photo-modal-image';
            modalImage.style.cursor = 'zoom-in';
        }

        // Click on modal background to close
        document.getElementById('photoModal').addEventListener('click', function(e) {
            if (e.target === this || e.target === document.getElementById('photoModalImage')) {
                closePhotoModal();
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape to close
            if (e.key === 'Escape') {
                const modal = document.getElementById('photoModal');
                if (modal.classList.contains('active')) {
                    closePhotoModal();
                }
            }
            // Zoom shortcuts
            if (document.getElementById('photoModal').classList.contains('active')) {
                if (e.key === '+' || e.key === '=') {
                    e.preventDefault();
                    zoomIn();
                } else if (e.key === '-') {
                    e.preventDefault();
                    zoomOut();
                } else if (e.key === '0') {
                    e.preventDefault();
                    resetZoom();
                }
            }
        });

        // Mouse wheel zoom for modal
        document.getElementById('photoModal').addEventListener('wheel', function(e) {
            if (this.classList.contains('active')) {
                e.preventDefault();
                if (e.deltaY < 0) {
                    zoomIn();
                } else {
                    zoomOut();
                }
            }
        }, { passive: false });
    </script>

    <?php include '../footer.php'; ?>
</body>

</html>