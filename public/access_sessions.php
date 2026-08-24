<?php
// access_sessions.php – Session validation + user data + earnings + daily bonus

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../DB_Conn/config.php';

// ✅ FIX: Use unified session variables
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'Customer') {
    $_SESSION['logout_message'] = 'Access requires login. Please sign in or create an account.';
    header('Location: ../login.php');
    exit();
}

// Fetch full user data from customers table
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]); // Use user_id instead of customer_id
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: ../login.php');
    exit();
}

$currentDateTime = date('D, j M Y g:i A');

// Daily login bonus
$storedDate = $user['last_login_date'] ?? '';
if ($storedDate !== $currentDateTime) {
    $updateStmt = $pdo->prepare("UPDATE customers SET last_login_date = ? WHERE id = ?");
    $updateStmt->execute([$currentDateTime, $_SESSION['user_id']]);

    // Refresh user data after update
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
}

// Update status to online
$stmt = $pdo->prepare("UPDATE customers SET status = 1 WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);

// Store user data in session for easy access
$_SESSION['customer_data'] = [
    'id' => $user['id'],
    'acc_number' => $user['acc_number'],
    'f_name' => $user['f_name'],
    'phone_number' => $user['phone_number'],
    'role' => $user['role'],
    'email' => $user['email'] ?? '',
    'profile' => $user['profile'] ?? 'profile.jpg'
];
