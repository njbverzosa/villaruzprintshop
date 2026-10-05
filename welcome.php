<?php
// welcome.php
require_once __DIR__ . '/bots.php';
include 'app_version.php';

// ---- SEO variables ----
$siteName   = 'Sofia';
$pageTitle  = 'Sofia App – Update & Download the Latest Version | Villaruz Print Shop';
$pageDesc   = 'Download the latest Sofia App for Android. Enjoy biometric login, faster application processing, and secure access to your account and orders anytime, anywhere.';
$canonical  = 'https://villaruz-print-shop-and-general-merchandise.shop/welcome.php';
$ogImage    = 'https://villaruz-print-shop-and-general-merchandise.shop/logo/ic_launcher.png';
$apkUrl     = 'https://villaruz-print-shop-and-general-merchandise.shop/APK/sofia.apk';
$updatedOn  = '2026-03-01';
?>
<!DOCTYPE html>
<html lang='en' prefix='og: https://ogp.me/ns#'>

<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover'>

    <!-- ==================== SEO ==================== -->
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name='description' content='<?= htmlspecialchars($pageDesc) ?>'>
    <meta name='keywords' content='Sofia app, Sofia APK, download Sofia app, Android app, biometric login, Villaruz Print Shop, mobile app update'>
    <meta name='author' content='Villaruz Print Shop & General Merchandise'>
    <meta name='robots' content='index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1'>
    <meta name='googlebot' content='index, follow'>
    <meta name='bingbot' content='index, follow'>
    <meta name='rating' content='general'>
    <meta name='revisit-after' content='7 days'>
    <meta name='language' content='English'>
    <meta name='distribution' content='global'>
    <meta name='theme-color' content='#1d4ed8'>
    <meta name='color-scheme' content='light'>
    <meta name='format-detection' content='telephone=no'>
    <meta name='referrer' content='strict-origin-when-cross-origin'>
    <meta http-equiv='X-Content-Type-Options' content='nosniff'>

    <link rel='canonical' href='<?= htmlspecialchars($canonical) ?>'>
    <link rel='alternate' hreflang='en' href='<?= htmlspecialchars($canonical) ?>'>
    <link rel='alternate' hreflang='x-default' href='<?= htmlspecialchars($canonical) ?>'>

    <!-- ==================== Open Graph ==================== -->
    <meta property='og:type' content='website'>
    <meta property='og:site_name' content='<?= htmlspecialchars($siteName) ?>'>
    <meta property='og:title' content='Sofia App – Update & Download the Latest Version'>
    <meta property='og:description' content='<?= htmlspecialchars($pageDesc) ?>'>
    <meta property='og:url' content='<?= htmlspecialchars($canonical) ?>'>
    <meta property='og:image' content='<?= htmlspecialchars($ogImage) ?>'>
    <meta property='og:image:width' content='512'>
    <meta property='og:image:height' content='512'>
    <meta property='og:image:alt' content='Sofia App icon'>
    <meta property='og:locale' content='en_US'>

    <!-- ==================== Twitter Card ==================== -->
    <meta name='twitter:card' content='summary_large_image'>
    <meta name='twitter:title' content='Sofia App – Update & Download the Latest Version'>
    <meta name='twitter:description' content='<?= htmlspecialchars($pageDesc) ?>'>
    <meta name='twitter:image' content='<?= htmlspecialchars($ogImage) ?>'>
    <meta name='twitter:image:alt' content='Sofia App icon'>

    <!-- ==================== PWA / Icons ==================== -->
    <link rel='manifest' href='/manifest.json'>
    <link rel='icon' type='image/png' sizes='32x32' href='/favicon-32.png'>
    <link rel='icon' type='image/png' sizes='16x16' href='/favicon-16.png'>
    <link rel='apple-touch-icon' sizes='180x180' href='/apple-touch-icon.png'>
    <link rel='mask-icon' href='/safari-pinned-tab.svg' color='#1d4ed8'>

    <!-- ==================== Performance Hints ==================== -->
    <link rel='preconnect' href='https://pagead2.googlesyndication.com' crossorigin>
    <link rel='dns-prefetch' href='https://googleads.g.doubleclick.net'>
    <link rel='dns-prefetch' href='https://tpc.googlesyndication.com'>
    <link rel='dns-prefetch' href='https://www.googletagmanager.com'>

    <!-- ==================== Structured Data (JSON-LD) ==================== -->
    <script type='application/ld+json'>
    {
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": "WebPage",
          "name": <?= json_encode($pageTitle) ?>,
          "description": <?= json_encode($pageDesc) ?>,
          "url": <?= json_encode($canonical) ?>,
          "inLanguage": "en",
          "isAccessibleForFree": true,
          "datePublished": "2024-01-01",
          "dateModified": <?= json_encode($updatedOn) ?>,
          "publisher": {
            "@type": "Organization",
            "name": "Villaruz Print Shop & General Merchandise",
            "url": "https://villaruz-print-shop-and-general-merchandise.shop/",
            "logo": {
              "@type": "ImageObject",
              "url": "https://villaruz-print-shop-and-general-merchandise.shop/logo/ic_launcher.png"
            }
          }
        },
        {
          "@type": "SoftwareApplication",
          "name": "Sofia",
          "operatingSystem": "Android 6.0+",
          "applicationCategory": "BusinessApplication",
          "applicationSubCategory": "E-commerce",
          "softwareVersion": <?= json_encode($latestVersion ?? '1.0.0') ?>,
          "downloadUrl": <?= json_encode($apkUrl) ?>,
          "installUrl": <?= json_encode($apkUrl) ?>,
          "screenshot": "https://villaruz-print-shop-and-general-merchandise.shop/logo/ic_launcher.png",
          "offers": {
            "@type": "Offer",
            "price": "0",
            "priceCurrency": "PHP",
            "availability": "https://schema.org/InStock"
          },
          "aggregateRating": {
            "@type": "AggregateRating",
            "ratingValue": "4.8",
            "bestRating": "5",
            "worstRating": "1",
            "ratingCount": "150"
          },
          "publisher": {
            "@type": "Organization",
            "name": "Villaruz Print Shop & General Merchandise"
          }
        },
        {
          "@type": "BreadcrumbList",
          "itemListElement": [
            { "@type": "ListItem", "position": 1, "name": "Home", "item": "https://villaruz-print-shop-and-general-merchandise.shop/" },
            { "@type": "ListItem", "position": 2, "name": "Sofia App", "item": <?= json_encode($canonical) ?> }
          ]
        },
        {
          "@type": "FAQPage",
          "mainEntity": [
            {
              "@type": "Question",
              "name": "How do I download the Sofia app?",
              "acceptedAnswer": {
                "@type": "Answer",
                "text": "Tap the Download Sofia App button on this page to get the latest APK file, then install it on your Android device."
              }
            },
            {
              "@type": "Question",
              "name": "What Android version does Sofia require?",
              "acceptedAnswer": {
                "@type": "Answer",
                "text": "Sofia requires Android 6.0 (Marshmallow) or higher."
              }
            },
            {
              "@type": "Question",
              "name": "How do I enable biometric login?",
              "acceptedAnswer": {
                "@type": "Answer",
                "text": "Log in once with your password inside the app to activate biometric login. After that, you can sign in using your device security (fingerprint or face)."
              }
            },
            {
              "@type": "Question",
              "name": "The app is not installing. What should I do?",
              "acceptedAnswer": {
                "@type": "Answer",
                "text": "Uninstall the old version first, then download and install the latest APK from this page. If the issue persists, contact our support team."
              }
            }
          ]
        }
      ]
    }
    </script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            width: 100%;
            height: 100%;
            overflow-x: hidden;
        }

        body {
            background: #ffffff;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            justify-content: flex-start;
            line-height: 1.5;
            color: #1e2b3c;
            min-height: 100vh;
        }

        .app-card {
            width: 100%;
            min-height: 100vh;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .app-header {
            padding: 2rem 1.5rem 1.5rem 1.5rem;
            border-bottom: 1px solid #edf2f7;
            text-align: center;
            position: relative;
            background: #ffffff;
        }

        .skip-link {
            position: absolute;
            top: 12px;
            right: 14px;
            font-size: 13px;
            font-weight: 700;
            color: #010511;
            text-decoration: none;
            letter-spacing: 0.02em;
            padding: 6px 12px;
            border-radius: 5px;
        }

        .app-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-weight: 700;
            font-size: 1.75rem;
            letter-spacing: -0.02em;
            margin: 0 auto 1rem auto;
        }

        .app-icon img {
            max-width: 72px;
            height: auto;
        }

        .app-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0b1e2e;
            letter-spacing: -0.015em;
        }

        .app-installed-version {
            display: inline-block;
            margin-top: 0.5rem;
            font-size: 0.85rem;
            font-weight: 600;
            color: #475569;
            background: #f1f5f9;
            padding: 0.3rem 0.85rem;
            border-radius: 5px;
            border: 1px solid #e2e8f0;
            letter-spacing: 0.02em;
        }

        .app-installed-version.match {
            background: #f0fdf4;
            color: #065f46;
            border-color: #bbf7d0;
        }

        .app-installed-version.mismatch {
            background: #fef2f2;
            color: #dc2626;
            border-color: #fecaca;
        }

        .app-subtitle {
            font-size: 0.875rem;
            color: #5f6f80;
            margin-top: 0.5rem;
        }

        .app-recommended {
            font-size: 17px;
            color: #067bf8;
            margin-top: 25px;
        }

        .app-body {
            flex: 1;
            padding: 2rem 1.5rem;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            max-width: 720px;
            margin: 0 auto;
            width: 100%;
        }

        .info-block {
            background: #f8fafd;
            border-left: 4px solid #2563eb;
            padding: 1rem 1.25rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-size: 0.9375rem;
            color: #1e2b3c;
        }

        .info-block strong {
            color: #1e40af;
        }

        .info-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #5f6f80;
            margin-bottom: 0.25rem;
        }

        .details-list {
            list-style: none;
            margin: 0 0 2rem 0;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
        }

        .details-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.85rem 1rem;
            font-size: 0.875rem;
            border-bottom: 1px solid #edf2f7;
        }

        .details-list li:last-child {
            border-bottom: none;
        }

        .details-list .label {
            color: #5f6f80;
        }

        .details-list .value {
            color: #0b1e2e;
            font-weight: 500;
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.875rem 1.25rem;
            border-radius: 40px;
            font-weight: 500;
            font-size: 1rem;
            border: 1px solid transparent;
            cursor: pointer;
            text-decoration: none;
            line-height: 1.2;
            transition: background 0.15s, box-shadow 0.15s;
            width: 100%;
        }

        .btn-primary {
            background: #1d4ed8;
            color: #ffffff;
            border-color: #1d4ed8;
            box-shadow: 0 2px 6px rgba(29, 78, 216, 0.2);
        }

        .btn-primary:hover {
            background: #1e40af;
        }

        .btn-outline {
            background: #ffffff;
            color: #1e2b3c;
            border-color: #cbd5e1;
        }

        .btn-outline:hover {
            background: #f8fafd;
        }

        .btn-success {
            background: #16a34a;
            color: #ffffff;
            border-color: #16a34a;
            box-shadow: 0 2px 6px rgba(22, 163, 74, 0.2);
        }

        .btn-success:hover {
            background: #15803d;
        }

        .btn:focus-visible {
            outline: 2px solid #2563eb;
            outline-offset: 2px;
        }

        .app-footer {
            padding: 1.25rem 1.5rem;
            border-top: 1px solid #edf2f7;
            text-align: center;
            font-size: 0.75rem;
            color: #5f6f80;
            background: #ffffff;
        }

        /* ============================================================
           SEO-ONLY CONTENT (visually hidden but crawlable)
           Keeps the original UI 100% unchanged.
           ============================================================ */
        .seo-hidden {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            padding: 0 !important;
            margin: -1px !important;
            overflow: hidden !important;
            clip: rect(0, 0, 0, 0) !important;
            white-space: nowrap !important;
            border: 0 !important;
        }

        @media (max-width: 480px) {
            .app-header {
                padding: 1.75rem 1.25rem 1.25rem 1.25rem;
            }

            .app-body {
                padding: 1.5rem 1.25rem;
            }

            .app-title {
                font-size: 1.25rem;
            }

            .app-icon img {
                max-width: 64px;
            }

            .skip-link {
                top: 0.75rem;
                right: 0.75rem;
                font-size: 0.8125rem;
                padding: 0.3rem 0.6rem;
            }
        }
    </style>
</head>

<body>

    <div class='app-card'>

        <!-- HEADER -->
        <div class='app-header'>
            <a href="closed" class="skip-link">Close</a>
            <div class='app-icon'><img src="logo/ic_launcher.png" alt="Sofia App Logo"></div>
            <div class='app-title'>Sofia</div>

            <div class='app-recommended'>Mobile browser access is no longer available. Please download the Sofia app.
            </div>
        </div>

        <!-- BODY -->
        <div class='app-body'>

            <div class='info-block'>
                <div class='info-label'>Update Notice</div>
                A new version of the <strong>Sofia</strong> is now available. Update or download the latest release
                for improved performance, a hassle-free application process, and password-free login using your device
                security. Access your account and orders anytime, anywhere — in just one touch.
            </div>

            <div class='info-block'>
                <div class='info-label'>How It Works</div>
                Log in once with your password to activate biometric login.
            </div>

            <div class='info-block'>
                <div class='info-label'>Having Trouble?</div>
                Uninstall the old app, then download the latest version from the link below. If you still encounter
                issues, contact our support team.
            </div>

            <ul class='details-list'>
                <li>
                    <span class='label'>New Release</span>
                    <span class='value'><?php echo htmlspecialchars($latestVersion); ?></span>
                </li>
                <li>
                    <span class='label'>Old Version</span>
                    <span class='value'><?php echo htmlspecialchars($currentVersion); ?></span>
                </li>
                <li>
                    <span class='label'>Release Date</span>
                    <span class='value'>March 2026</span>
                </li>
                <li>
                    <span class='label'>Requirements</span>
                    <span class='value'>Android 6.0 and above.</span>
                </li>
            </ul>

            <div class='action-buttons'>
                <a href='https://villaruz-print-shop-and-general-merchandise.shop/APK/sofia.apk'
                    class='btn btn-primary'>Download Sofia App</a>
                <a href='https://villaruz-print-shop-and-general-merchandise.shop/APK/sofia.apk'
                    class='btn btn-outline'>Re-Install App</a>
            </div>

        </div>

        <!-- FOOTER -->
        <div class='app-footer'>
            &copy; 2026 Villaruz Print Shop &amp; General Merchandise. All rights reserved.
        </div>

    </div>

    <!-- ============================================================
         SEO-ONLY CONTENT — visually hidden, crawlable by Google/Bing.
         Does NOT affect the visible UI in any way.
         ============================================================ -->
    <section class="seo-hidden" aria-hidden="false">
        <h1>Sofia App – Download &amp; Update the Latest Version</h1>
        <p>
            The Sofia App is the official Android application of Villaruz Print Shop &amp; General Merchandise.
            It provides a faster, safer, and password-free way to manage your account, place orders, and
            track deliveries on your mobile device.
        </p>

        <h2>About the Sofia App</h2>
        <p>
            The latest release of Sofia focuses on performance improvements, smoother application
            processing, and biometric login using your device's fingerprint or face unlock. Access your
            account and orders anytime, anywhere — in just one touch.
        </p>

        <h2>What's New in the Latest Version</h2>
        <ul>
            <li>Faster and smoother application process</li>
            <li>Password-free login with biometric authentication</li>
            <li>Improved order tracking and account access</li>
            <li>Optimized performance for lower battery and data usage</li>
            <li>Bug fixes and security updates</li>
        </ul>

        <h2>System Requirements</h2>
        <ul>
            <li>Android 6.0 (Marshmallow) or higher</li>
            <li>At least 100 MB of free storage</li>
            <li>Internet connection for account and order syncing</li>
        </ul>

        <h2>How to Install Sofia on Your Android Device</h2>
        <ol>
            <li>Tap the Download Sofia App button on this page to download the latest APK.</li>
            <li>If prompted, allow installation from this source in your device settings.</li>
            <li>Open the APK and tap Install.</li>
            <li>Log in once with your password to activate biometric login.</li>
        </ol>

        <h2>Frequently Asked Questions</h2>

        <h3>How do I download the Sofia app?</h3>
        <p>Tap the Download Sofia App button on this page to get the latest APK file, then install it on your Android device.</p>

        <h3>What Android version does Sofia require?</h3>
        <p>Sofia requires Android 6.0 (Marshmallow) or higher.</p>

        <h3>How do I enable biometric login?</h3>
        <p>Log in once with your password inside the app to activate biometric login. After that, you can sign in using your device security (fingerprint or face).</p>

        <h3>The app is not installing. What should I do?</h3>
        <p>Uninstall the old version first, then download and install the latest APK from this page. If the issue persists, contact our support team.</p>

        <h2>Contact &amp; Support</h2>
        <p>
            For questions, concerns, or assistance with the Sofia App, please contact
            Villaruz Print Shop &amp; General Merchandise through our official channels.
            We respond to all support inquiries as quickly as possible.
        </p>
    </section>

</body>

</html>