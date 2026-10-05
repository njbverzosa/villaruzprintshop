<?php
// ==============================================
// bot-access.php — Styled crawler access log email
// Rendered via: ob_start(); include 'bot-access.php'; $mail->Body = ob_get_clean();
// Expects $name and $type to be set by the caller (logAccess()).
// ==============================================

// ---- Collect & sanitize server data ----
$botType = htmlspecialchars($type ?? 'Crawler', ENT_QUOTES, 'UTF-8');
$botName = htmlspecialchars($name ?? 'Unknown Bot', ENT_QUOTES, 'UTF-8');

$ip       = htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', ENT_QUOTES, 'UTF-8');
$ua       = htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? '(none)', ENT_QUOTES, 'UTF-8');
$uri      = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/', ENT_QUOTES, 'UTF-8');
$host     = htmlspecialchars($_SERVER['HTTP_HOST'] ?? '-', ENT_QUOTES, 'UTF-8');
$referer  = htmlspecialchars(
    !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'Direct Access',
    ENT_QUOTES,
    'UTF-8'
);
$method   = htmlspecialchars($_SERVER['REQUEST_METHOD'] ?? 'GET', ENT_QUOTES, 'UTF-8');
$protocol = htmlspecialchars($_SERVER['SERVER_PROTOCOL'] ?? '-', ENT_QUOTES, 'UTF-8');
$lang     = htmlspecialchars($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '(none)', ENT_QUOTES, 'UTF-8');
$encoding = htmlspecialchars($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '(none)', ENT_QUOTES, 'UTF-8');
$conn     = htmlspecialchars($_SERVER['HTTP_CONNECTION'] ?? '(none)', ENT_QUOTES, 'UTF-8');
$rdns     = htmlspecialchars(@gethostbyaddr($_SERVER['REMOTE_ADDR'] ?? '') ?: '(unresolved)', ENT_QUOTES, 'UTF-8');
$time     = htmlspecialchars(date('D, j M Y h:i A'), ENT_QUOTES, 'UTF-8');

// ---- Badge color by type ----
$badgeBg = '#eef3ff';
$badgeFg = '#2f6bea';
if (stripos($botType, 'AdsBot') !== false) {
    $badgeBg = '#fff7ed';
    $badgeFg = '#c2410c';
} elseif (stripos($botType, 'Crawler') !== false) {
    $badgeBg = '#ecfdf5';
    $badgeFg = '#047857';
}

// ---- Build info table rows ----
$rows = [
    'Access Type'     => $botType,
    'Bot Name'        => $botName,
    'Detected At'     => $time,
    'HTTP Method'     => $method,
    'Protocol'        => $protocol,
    'Request URL'     => $host . $uri,
    'Referrer'        => $referer,
    'IP Address'      => $ip,
    'Reverse DNS'     => $rdns,
    'Accept-Language' => $lang,
    'Accept-Encoding' => $encoding,
    'Connection'      => $conn,
    'User Agent'      => $ua,
];

$tableRows = '';
foreach ($rows as $label => $value) {
    $tableRows .= "<tr>"
        . "<td class='row-label'>{$label}</td>"
        . "<td class='row-value'>{$value}</td>"
        . "</tr>";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $botType ?> Access Log</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');

        body {
            font-family: 'Poppins', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f8fafc;
            color: #334155;
        }

        .email-container {
            max-width: 620px;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        /* ============ HEADER ============ */
        .email-header {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            padding: 26px 30px;
            color: #ffffff;
            position: relative;
            overflow: hidden;
        }

        .email-header::before {
            content: '';
            position: absolute;
            top: -60px;
            right: -60px;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(79, 140, 255, .25) 0%, transparent 70%);
        }

        .email-header .eyebrow {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #7db0ff;
            margin-bottom: 6px;
            position: relative;
            z-index: 1;
        }

        .email-header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.3px;
            position: relative;
            z-index: 1;
        }

        .email-header p {
            margin: 6px 0 0 0;
            font-size: 13px;
            color: #b0c4de;
            font-weight: 500;
            position: relative;
            z-index: 1;
        }

        /* ============ BODY ============ */
        .email-body {
            padding: 26px 30px;
            line-height: 1.6;
        }

        .intro {
            font-size: 14px;
            color: #475569;
            margin: 0 0 18px 0;
            background: linear-gradient(135deg, #f8fafc 0%, #eef2f7 100%);
            border: 1px solid #e2e8f0;
            border-left: 4px solid #4f8cff;
            border-radius: 8px;
            padding: 14px 16px;
        }

        .intro strong {
            color: #1a2233;
            font-weight: 700;
        }

        /* ============ TYPE BADGE ============ */
        .type-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        /* ============ INFO TABLE ============ */
        .log-card {
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            margin-bottom: 20px;
        }

        .log-table {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
        }

        .log-table .row-label {
            padding: 10px 14px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            width: 140px;
            vertical-align: top;
        }

        .log-table .row-value {
            padding: 10px 14px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
            color: #0f172a;
            word-break: break-all;
            font-weight: 500;
        }

        .log-table tr:last-child .row-label,
        .log-table tr:last-child .row-value {
            border-bottom: none;
        }

        /* ============ FOOTER ============ */
        .footer {
            text-align: center;
            background: #f1f5f9;
            padding: 18px 20px;
            font-size: 12px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            font-weight: 500;
        }

        .footer strong {
            color: #475569;
        }
    </style>
</head>

<body>
    <div class="email-container">

        <!-- Header -->
        <div class="email-header">
            <div class="eyebrow">Crawler Activity</div>
            <h1>🤖 <?= $botType ?> Access Log</h1>
            <p>A <?= $botType ?> visited your site</p>
        </div>

        <!-- Body -->
        <div class="email-body">

            <div class="intro">
                A <strong><?= $botName ?></strong> got
                <strong><?= $referer ?></strong> to your
                <strong><?= $uri ?></strong> page with the following details:
            </div>

            <!-- Badge with inline colors (safer for email clients) -->
            <span class="type-badge" style="background:<?= $badgeBg ?>;color:<?= $badgeFg ?>;">
                <?= $botType ?>
            </span>

            <!-- Info table -->
            <div class="log-card">
                <table class="log-table">
                    <?= $tableRows ?>
                </table>
            </div>

        </div>

        <!-- Footer -->
        <div class="footer">
            <p style="margin: 0;">
                📧 This email was sent by <strong>Letsgrow</strong>.
            </p>
        </div>
    </div>
</body>

</html>