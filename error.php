<?php
// error.php
session_start();

// Get the error code
$error_code = http_response_code();

// Custom error messages
$error_messages = [
    400 => 'Bad Request',
    401 => 'Unauthorized Access',
    403 => 'Forbidden - Please Login to continue',
    404 => 'Page Not Found',
    500 => 'Internal Server Error',
    502 => 'Bad Gateway',
    503 => 'Service Unavailable'
];

// Redirect to ../login.php with error message
$error_title = $error_messages[$error_code] ?? 'An Error Occurred';

// Set session error message to display on login page
$_SESSION['login_error'] = 'Error ' . $error_code . ': ' . $error_title;

// Redirect to login.php
header('Location: ../login.php');
exit;
?>