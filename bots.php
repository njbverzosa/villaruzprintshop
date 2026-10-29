<?php
// ==============================================
// bots.php — Detect, log, and alert on crawlers
// Include at the VERY TOP of any page you want monitored:
//
//     require_once __DIR__ . '/bots.php';
//
// Must be included BEFORE session_start() and any login guards,
// so that crawler hits are captured even for logged-out visitors.
// ==============================================

/**
 * Detect a known bot/crawler by User-Agent.
 * Returns the pretty name (e.g. "Googlebot") or false.
 */
function isBotOrCrawler()
{
    if (empty($_SERVER['HTTP_USER_AGENT'])) {
        return false;
    }

    $userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);

    // Order matters: check more specific keys first
    $botsAndCrawlers = [
        'googlebot-image',
        'googlebot-video',
        'googlebot-news',
        'googlebot-mobile',
        'googlebot-ads',
        'adsbot-google',
        'mediapartners-google',
        'googlebot',
        'bingbot',
        'slurp',
        'duckduckbot',
        'baiduspider',
        'yandexbot',
    ];

    foreach ($botsAndCrawlers as $botOrCrawler) {
        if (strpos($userAgent, $botOrCrawler) !== false) {
            // "googlebot-image" → "Googlebot Image"
            return ucwords(str_replace('-', ' ', $botOrCrawler));
        }
    }
    return false;
}

/**
 * Rate-limit alerts: one email per bot-name per hour.
 * Uses a lock file in the system temp directory.
 */
function botAlreadyAlertedThisHour(string $botName): bool
{
    $lockFile = sys_get_temp_dir()
        . '/bot_alert_'
        . md5($botName)
        . '_'
        . date('Y-m-d-H');

    if (file_exists($lockFile)) {
        return true;
    }
    @file_put_contents($lockFile, (string) time());
    return false;
}

/**
 * Log the crawler hit to file and (rate-limited) send an alert email.
 */
function logAccess(string $name, string $type): void
{
    // ============================================
    // 1) LOG TO FILE (absolute path)
    // ============================================

    $logMessage = date('D, j M Y h:i A') . " - {$type}: {$name} accessed the site.\n"
        . "Request URL: " . ($_SERVER['REQUEST_URI'] ?? '-') . "\n"
        . "Referrer: " . (!empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'Direct Access') . "\n"
        . "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? '-') . "\n"
        . "IP Address: " . ($_SERVER['REMOTE_ADDR'] ?? '-') . "\n"
        . str_repeat('-', 50) . "\n";

    if (@file_put_contents($logMessage, FILE_APPEND) === false) {
        error_log("Failed to log {$type} access.");
    }
    // ============================================
    // 2) RATE-LIMIT EMAIL
    // ============================================
    if (botAlreadyAlertedThisHour($name)) {
        return;
    }

    // ============================================
    // 3) SEND EMAIL
    // ============================================
//     try {
//         require_once __DIR__ . '/Mail/PHPMailerAutoload.php';

//         $mail = new PHPMailer;
//         $mail->isSMTP();
//         $mail->Host = 'smtp.gmail.com';
//         $mail->Port = 587;
//         $mail->SMTPAuth = true;
//         $mail->SMTPSecure = 'tls';
//         $mail->Username = 'villaruzsofiaapp@gmail.com';
//         $mail->Password = 'pjip iqic xpdq xjqf';
//         $mail->setFrom('villaruzsofiaapp@gmail.com', 'Sofia Bots Alert');
//         $mail->addAddress('njbverzosa@gmail.com');

//         $mail->isHTML(true);
//         $mail->Subject = "Sofia - {$type} Access: {$name}";

//         // ✅ Render bot-access.php with $type and $name in scope
//         ob_start();
//         include __DIR__ . '/bot-access.php';
//         $mail->Body = ob_get_clean();

//         $mail->AltBody = "A {$type} ({$name}) accessed "
//             . ($_SERVER['REQUEST_URI'] ?? '/')
//             . " from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

//         if (!$mail->send()) {
//             throw new Exception('Mail could not be sent. ' . $mail->ErrorInfo);
//         }
//     } catch (Exception $e) {
//         error_log("Failed to send bot alert email: " . $e->getMessage());
//     }
// }

// ================================================
// AUTO-RUN ON INCLUDE
// ================================================
$__botName = isBotOrCrawler();
if ($__botName) {
    $__botType = (stripos($__botName, 'adsbot') !== false) ? 'AdsBot' : 'Crawler';
    logAccess($__botName, $__botType);
}