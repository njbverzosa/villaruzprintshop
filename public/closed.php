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
// 3. GET USER DATA FROM SESSION (MOVE THIS UP)
// ==============================================
$userRole = $_SESSION['user_role'];
$userId = $_SESSION['user_id'];
$accNumber = $_SESSION['acc_number'];

// ==============================================
// 4. FETCH USER DATA FROM DATABASE
// ==============================================
$userData = null;
if ($userRole === 'Customer') {
    $stmt = $pdo->prepare("SELECT id, acc_number, online_time, f_name, email, phone_number, vip FROM customers WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$userData) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

// public/closed.php
date_default_timezone_set('Asia/Manila');
$currentTime = date('M j, g:i A'); // e.g., Aug 31, 2:30 PM

if ($userRole === 'Customer') {
    $updateStmt = $pdo->prepare("UPDATE customers SET online_time = ? WHERE id = ?");
    $updateStmt->execute([$currentTime, $userData['id']]);
}

// ==============================================
// 6. DESTROY SESSION COMPLETELY
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
// 7. SET EXIT MESSAGE AND REDIRECT
// ==============================================
// Start a new session to store the exit message
session_start();

// Set the exit message
$_SESSION['exit_message'] = 'Session ended';

// Redirect to login page (go up one level from public folder)
header('Location: ../login.php');
exit;
?>