<?php
// public/closed.php - Logout handler for Villaruz Print Shop

session_start();

// ==============================================
// 1. FIX PATHS - config.php is in DB_Conn folder at root level
// ==============================================
require_once __DIR__ . '/../DB_Conn/config.php';

// ==============================================
// 2. CHECK IF USER IS LOGGED IN
// ==============================================
function isLoggedIn()
{
    return isset($_SESSION['user_role']) &&
        isset($_SESSION['user_id']) &&
        isset($_SESSION['acc_number']);
}

// If not logged in, redirect to login
if (!isLoggedIn()) {
    $_SESSION['login_error'] = 'Please login first.';
    header('Location: ../login.php');
    exit;
}

// ==============================================
// 3. GET USER DATA FROM SESSION
// ==============================================
$userRole = $_SESSION['user_role'];
$userId = $_SESSION['user_id'];
$accNumber = $_SESSION['acc_number'];

// ==============================================
// 4. UPDATE USER STATUS TO OFFLINE (0) WITH ONLINE TIME
// ==============================================
try {
    // Set timezone to Asia/Manila
    date_default_timezone_set('Asia/Manila');

    // Get current time in 12-hour format with AM/PM
    $currentTime = date('g:i A'); // e.g., 2:30 PM

    // Update customer status to 0 (offline) and set online_time
    $stmt = $pdo->prepare("UPDATE customers SET status = 0, online_time = ? WHERE id = ?");
    $stmt->execute([$currentTime, $userId]);
} catch (Exception $e) {
}

// ==============================================
// 5. DESTROY SESSION COMPLETELY
// ==============================================
// Clear all session variables
$_SESSION = array();

// If session cookie exists, delete it
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy the session
session_destroy();


// ==============================================
// 6. SET EXIT MESSAGE AND REDIRECT
// ==============================================
// Start a new session to store the exit message
session_start();

// Set the exit message
$_SESSION['exit_message'] = 'Session ended';

// Redirect to login page (go up one level from public folder)
header('Location: ../login.php');
exit;
?>