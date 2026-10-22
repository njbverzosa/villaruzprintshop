<?php
require_once __DIR__ . '/bots.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Villaruz Print Shop & General Merchandise | Modern Print & Retail</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-3766494953280597"
     crossorigin="anonymous"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800;14..32,900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            /* New refined palette */
            --bg-main: #FAFAF8;
            --bg-soft: #F3F4F1;
            --bg-card: #FFFFFF;

            --ink-900: #0F1B21;
            --ink-800: #1A2A32;
            --ink-700: #2A3F4A;
            --ink-600: #3F5661;
            --ink-500: #5C7280;
            --ink-400: #8A9BA6;
            --ink-300: #B8C4CC;

            --brand: #0F4C5C;
            --brand-dark: #093846;
            --brand-light: #E6F0F3;

            --accent: #E36414;
            --accent-dark: #C5530D;
            --accent-soft: #FFF1E6;

            --border: #E5E9EB;
            --border-strong: #D3DADF;

            --shadow-xs: 0 1px 2px rgba(15, 27, 33, 0.04);
            --shadow-sm: 0 4px 12px -2px rgba(15, 27, 33, 0.06), 0 2px 4px -2px rgba(15, 27, 33, 0.03);
            --shadow-md: 0 12px 28px -8px rgba(15, 27, 33, 0.10), 0 4px 8px -4px rgba(15, 27, 33, 0.04);
            --shadow-lg: 0 24px 48px -12px rgba(15, 27, 33, 0.15);

            --radius-sm: 10px;
            --radius: 16px;
            --radius-lg: 24px;
            --radius-xl: 32px;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
            background-color: var(--bg-main);
            color: var(--ink-800);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        ::-webkit-scrollbar {
            width: 10px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-soft);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--ink-400);
            border-radius: 10px;
            border: 2px solid var(--bg-soft);
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--brand);
        }

        /* ============================================== */
        /* NAVIGATION */
        /* ============================================== */
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 6%;
            background: rgba(250, 250, 248, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo img {
            width: 100px;
            height: auto;
            max-width: 100%;
            object-fit: contain;
            display: block;
        }

        .btn-order {
            background: var(--ink-900);
            border: none;
            padding: 11px 26px;
            border-radius: 100px;
            font-weight: 600;
            color: white;
            cursor: pointer;
            transition: all 0.25s ease;
            text-decoration: none;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            letter-spacing: -0.01em;
            box-shadow: var(--shadow-sm);
        }

        .btn-order:hover {
            background: var(--brand);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        /* ============================================== */
        /* HERO */
        /* ============================================== */
        .hero {
            position: relative;
            padding: 90px 6% 100px;
            background: var(--bg-main);
            overflow: hidden;
        }

        .hero::before {
            content: "";
            position: absolute;
            top: -200px;
            right: -200px;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(227, 100, 20, 0.08), transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .hero::after {
            content: "";
            position: absolute;
            bottom: -200px;
            left: -200px;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(15, 76, 92, 0.08), transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .hero-inner {
            position: relative;
            max-width: 900px;
            margin: 0 auto;
            text-align: center;
            z-index: 1;
        }

        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            padding: 7px 16px;
            border-radius: 100px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--ink-600);
            margin-bottom: 24px;
            box-shadow: var(--shadow-xs);
        }

        .hero-eyebrow i {
            color: var(--accent);
            font-size: 0.75rem;
        }

        .hero-title {
            font-size: clamp(36px, 6vw, 68px);
            font-weight: 800;
            line-height: 1.05;
            margin-bottom: 24px;
            color: var(--ink-900);
            letter-spacing: -0.03em;
        }

        .hero-title .gradient {
            background: linear-gradient(120deg, var(--brand) 0%, var(--accent) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            color: transparent;
        }

        .hero-desc {
            font-size: 1.1rem;
            color: var(--ink-600);
            margin: 0 auto 40px;
            max-width: 620px;
            line-height: 1.65;
        }

        .hero-buttons {
            display: flex;
            gap: 14px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-primary,
        .btn-secondary,
        .btn-download {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 32px;
            border-radius: 100px;
            font-weight: 600;
            font-size: 0.98rem;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            letter-spacing: -0.01em;
        }

        .btn-primary {
            background: var(--brand);
            color: white;
            border: none;
            box-shadow: 0 8px 20px -8px rgba(15, 76, 92, 0.5);
        }

        .btn-primary:hover {
            background: var(--brand-dark);
            transform: translateY(-2px);
            box-shadow: 0 12px 28px -8px rgba(15, 76, 92, 0.6);
        }

        .btn-secondary {
            background: transparent;
            border: 1.5px solid var(--border-strong);
            color: var(--ink-800);
        }

        .btn-secondary:hover {
            background: var(--bg-card);
            border-color: var(--ink-800);
            transform: translateY(-2px);
        }

        .btn-download {
            background: var(--accent);
            color: white;
            border: none;
            box-shadow: 0 8px 20px -8px rgba(227, 100, 20, 0.55);
        }

        .btn-download:hover {
            background: var(--accent-dark);
            transform: translateY(-2px);
            box-shadow: 0 12px 28px -8px rgba(227, 100, 20, 0.65);
        }

        .btn-download i {
            font-size: 1rem;
        }

        /* ============================================== */
        /* SECTION HEADS */
        /* ============================================== */
        .section-head {
            text-align: center;
            margin-bottom: 56px;
            padding: 0 6%;
        }

        .section-tag {
            display: inline-block;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--accent);
            letter-spacing: 0.12em;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .section-title {
            font-size: clamp(30px, 4.5vw, 46px);
            font-weight: 800;
            margin-bottom: 14px;
            color: var(--ink-900);
            letter-spacing: -0.025em;
            line-height: 1.1;
        }

        .section-title span {
            color: var(--accent);
        }

        .section-sub {
            color: var(--ink-500);
            font-size: 1rem;
            max-width: 620px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* ============================================== */
        /* FEATURES */
        /* ============================================== */
        .features {
            padding: 90px 6%;
            background: var(--bg-card);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .feature-card {
            position: relative;
            background: var(--bg-main);
            padding: 36px 28px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }

        .feature-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--brand), var(--accent));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
        }

        .feature-card:hover {
            background: var(--bg-card);
            border-color: var(--border-strong);
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 56px;
            height: 56px;
            border-radius: 16px;
            background: var(--brand-light);
            color: var(--brand);
            font-size: 22px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .feature-card:hover .feature-icon {
            background: var(--brand);
            color: white;
            transform: scale(1.05);
        }

        .feature-card h3 {
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--ink-900);
            letter-spacing: -0.015em;
        }

        .feature-card p {
            color: var(--ink-500);
            font-size: 0.92rem;
            line-height: 1.6;
        }

        /* ============================================== */
        /* CAROUSEL */
        /* ============================================== */
        .carousel-section {
            padding: 90px 6%;
            background: var(--bg-main);
        }

        .carousel-container {
            max-width: 900px;
            margin: 0 auto;
            position: relative;
            overflow: hidden;
            border-radius: var(--radius-xl);
        }

        .carousel-slides {
            display: flex;
            transition: transform 0.55s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .carousel-slide {
            min-width: 100%;
            padding: 56px 40px;
            background: var(--bg-card);
            border-radius: var(--radius-xl);
            text-align: center;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }

        .carousel-slide .slide-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 72px;
            height: 72px;
            border-radius: 22px;
            background: var(--accent-soft);
            color: var(--accent);
            font-size: 28px;
            margin-bottom: 22px;
        }

        .carousel-slide p.lead {
            font-size: 1.35rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: var(--ink-900);
            letter-spacing: -0.02em;
            line-height: 1.3;
        }

        .carousel-slide p.desc {
            color: var(--ink-500);
            font-size: 1rem;
            max-width: 560px;
            margin: 0 auto 20px;
            line-height: 1.6;
        }

        .carousel-slide .badge {
            display: inline-block;
            background: var(--brand-light);
            padding: 6px 16px;
            border-radius: 100px;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--brand);
            margin-top: 4px;
            letter-spacing: 0.01em;
        }

        .carousel-slide small {
            display: block;
            color: var(--ink-400);
            font-size: 0.82rem;
            margin-top: 14px;
        }

        .carousel-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: var(--bg-card);
            border: 1px solid var(--border);
            width: 44px;
            height: 44px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-sm);
            color: var(--ink-700);
        }

        .carousel-btn:hover {
            background: var(--ink-900);
            color: white;
            border-color: var(--ink-900);
            transform: translateY(-50%) scale(1.08);
        }

        .carousel-btn.prev {
            left: 20px;
        }

        .carousel-btn.next {
            right: 20px;
        }

        .carousel-dots {
            text-align: center;
            margin-top: 28px;
        }

        .dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--ink-300);
            margin: 0 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .dot.active {
            background: var(--accent);
            width: 28px;
            border-radius: 100px;
        }

        /* ============================================== */
        /* SERVICES */
        /* ============================================== */
        .services {
            padding: 90px 6%;
            background: var(--bg-card);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .service-card {
            background: var(--bg-main);
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid var(--border);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }

        .service-card:hover {
            background: var(--bg-card);
            border-color: var(--border-strong);
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .service-img {
            height: 140px;
            background: linear-gradient(135deg, var(--brand-light), var(--accent-soft));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 44px;
            color: var(--brand);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .service-img::after {
            content: "";
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.5), transparent 60%);
            pointer-events: none;
        }

        .service-card:hover .service-img {
            color: var(--accent);
        }

        .service-card:hover .service-img i {
            transform: scale(1.1) rotate(-5deg);
        }

        .service-img i {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            z-index: 1;
        }

        .service-info {
            padding: 24px 22px 26px;
            text-align: center;
        }

        .service-info h3 {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--ink-900);
            letter-spacing: -0.015em;
        }

        .service-info p {
            color: var(--ink-500);
            font-size: 0.88rem;
            line-height: 1.55;
        }

        /* ============================================== */
        /* FOOTER */
        /* ============================================== */
        footer {
            padding: 56px 6% 40px;
            background: var(--ink-900);
            color: var(--ink-300);
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }

        .footer-inner {
            max-width: 1200px;
            margin: 0 auto;
            text-align: center;
        }

        .footer-brand {
            font-size: 1.15rem;
            font-weight: 700;
            color: white;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }

        .footer-tagline {
            font-size: 0.85rem;
            color: var(--ink-400);
            margin-bottom: 24px;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            font-size: 0.82rem;
            margin-bottom: 20px;
        }

        .footer-links span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--ink-400);
        }

        .footer-links span i {
            color: var(--accent);
            font-size: 0.75rem;
        }

        .footer-sep {
            color: var(--ink-600);
            opacity: 0.5;
        }

        .copyright {
            text-align: center;
            font-size: 0.78rem;
            color: var(--ink-500);
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
        }

        /* ============================================== */
        /* APP BANNER (optional usage) */
        /* ============================================== */
        .app-banner {
            background: linear-gradient(135deg, var(--ink-900), var(--brand));
            color: white;
            padding: 18px 6%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .app-banner span {
            font-weight: 400;
        }

        .app-banner strong {
            color: var(--accent);
        }

        .app-banner .btn-sm {
            background: var(--accent);
            color: white;
            padding: 9px 22px;
            border-radius: 100px;
            font-weight: 600;
            text-decoration: none;
            font-size: 0.88rem;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .app-banner .btn-sm:hover {
            background: var(--accent-dark);
            transform: translateY(-1px);
        }

        /* ============================================== */
        /* RESPONSIVE */
        /* ============================================== */
        @media (max-width: 768px) {
            nav {
                padding: 14px 5%;
            }

            .logo img {
                width: 88px;
            }

            .btn-order {
                padding: 9px 20px;
                font-size: 0.82rem;
            }

            .hero {
                padding: 64px 5% 72px;
            }

            .hero-desc {
                font-size: 1rem;
            }

            .features,
            .carousel-section,
            .services {
                padding: 64px 5%;
            }

            .section-head {
                margin-bottom: 40px;
            }

            .carousel-btn {
                width: 38px;
                height: 38px;
            }

            .carousel-btn.prev {
                left: 10px;
            }

            .carousel-btn.next {
                right: 10px;
            }

            .app-banner {
                flex-direction: column;
                gap: 10px;
                padding: 14px 5%;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .hero-title {
                font-size: 2.1rem;
            }

            .btn-primary,
            .btn-secondary,
            .btn-download {
                font-size: 0.88rem;
                padding: 12px 22px;
                width: 100%;
                justify-content: center;
            }

            .hero-buttons {
                flex-direction: column;
                width: 100%;
            }

            .carousel-slide {
                padding: 40px 24px;
            }

            .carousel-slide .slide-icon {
                width: 60px;
                height: 60px;
                font-size: 24px;
            }

            .carousel-slide p.lead {
                font-size: 1.15rem;
            }

            .feature-card {
                padding: 28px 22px;
            }
        }
    

        /* ==============================================
           CATCHY E-COMMERCE THEME + IMAGE BACKGROUND
           Keep the page text readable over the background.
           Place ecommerce-bg.png beside the page using this CSS.
        ============================================== */
        :root {
            --bg-main: transparent;
            --bg-soft: rgba(7, 20, 48, 0.82);
            --bg-card: rgba(8, 24, 57, 0.82);
            --ink-900: #FFFFFF;
            --ink-800: #F4F8FF;
            --ink-700: #E5F0FF;
            --ink-600: #D1E4FF;
            --ink-500: #B7CBE8;
            --ink-400: #91AED6;
            --ink-300: #D8E8FF;
            --brand: #00C8FF;
            --brand-dark: #007FB8;
            --brand-light: rgba(0, 200, 255, 0.16);
            --accent: #FFB000;
            --accent-dark: #F07800;
            --accent-soft: rgba(255, 176, 0, 0.16);
            --border: rgba(145, 190, 255, 0.25);
            --border-strong: rgba(145, 210, 255, 0.55);
        }

        html, body {
            min-height: 100%;
        }

        body {
            color: var(--ink-800);
            background:
                linear-gradient(115deg, rgba(2, 8, 29, 0.94) 0%,
                    rgba(4, 18, 48, 0.82) 48%,
                    rgba(3, 15, 39, 0.60) 100%),
                url("ecommerce-bg.png") center center / cover fixed no-repeat;
            background-color: #031027;
        }

        nav {
            background: rgba(3, 12, 34, 0.86);
            border-bottom: 1px solid rgba(74, 190, 255, 0.35);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.22);
        }

        .hero {
            background: transparent;
        }

        .hero::before {
            background: radial-gradient(circle, rgba(0, 200, 255, 0.15), transparent 70%);
        }

        .hero::after {
            background: radial-gradient(circle, rgba(255, 176, 0, 0.10), transparent 70%);
        }

        .hero-eyebrow {
            color: #DDF6FF;
            background: rgba(4, 25, 58, 0.82);
            border-color: rgba(91, 202, 255, 0.42);
            box-shadow: 0 0 24px rgba(0, 200, 255, 0.10);
        }

        .hero-eyebrow i,
        .section-tag,
        .section-title span {
            color: #FFC247;
        }

        .hero-title {
            color: #FFFFFF;
            text-shadow: 0 4px 24px rgba(0, 0, 0, 0.40);
        }

        .hero-title .gradient {
            background: linear-gradient(110deg, #59E5FF 0%, #FFFFFF 48%, #FFC247 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            color: transparent;
        }

        .hero-desc {
            color: #D5E5FF;
            text-shadow: 0 2px 12px rgba(0, 0, 0, 0.65);
        }

        .btn-primary,
        .btn-secondary,
        .btn-download,
        .btn-order,
        .app-banner .btn-sm {
            position: relative;
            overflow: hidden;
            font-weight: 800;
            letter-spacing: 0.01em;
            border: 1px solid transparent;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.28),
                        0 0 18px rgba(0, 200, 255, 0.12);
        }

        .btn-primary {
            color: #031426;
            background: linear-gradient(135deg, #63E8FF, #00B8F0);
            border-color: rgba(181, 246, 255, 0.8);
            box-shadow: 0 8px 26px rgba(0, 184, 240, 0.35);
        }

        .btn-primary:hover {
            color: #FFFFFF;
            background: linear-gradient(135deg, #00A9E8, #0755C9);
            transform: translateY(-3px) scale(1.025);
            box-shadow: 0 12px 32px rgba(0, 174, 255, 0.42);
        }

        .btn-secondary {
            color: #FFFFFF;
            background: rgba(5, 23, 54, 0.66);
            border: 1px solid rgba(104, 218, 255, 0.72);
            backdrop-filter: blur(10px);
        }

        .btn-secondary:hover {
            color: #031426;
            background: #FFFFFF;
            border-color: #FFFFFF;
            transform: translateY(-3px);
        }

        .btn-download,
        .btn-order,
        .app-banner .btn-sm {
            color: #201300;
            background: linear-gradient(135deg, #FFE27A, #FF9D00);
            border-color: rgba(255, 236, 166, 0.9);
            box-shadow: 0 8px 26px rgba(255, 157, 0, 0.30);
        }

        .btn-download:hover,
        .btn-order:hover,
        .app-banner .btn-sm:hover {
            color: #FFFFFF;
            background: linear-gradient(135deg, #FF8A00, #F04B00);
            transform: translateY(-3px) scale(1.025);
            box-shadow: 0 12px 32px rgba(255, 126, 0, 0.40);
        }

        .features,
        .carousel-section,
        .services {
            background: rgba(3, 13, 36, 0.50);
            border-color: rgba(113, 190, 255, 0.20);
            backdrop-filter: blur(3px);
        }

        .section-title {
            color: #FFFFFF;
            text-shadow: 0 3px 18px rgba(0, 0, 0, 0.45);
        }

        .section-sub {
            color: #D0E2FF;
        }

        .feature-card,
        .service-card {
            color: #FFFFFF;
            background: rgba(5, 22, 53, 0.84);
            border-color: rgba(93, 188, 255, 0.28);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.20);
            backdrop-filter: blur(12px);
        }

        .feature-card h3,
        .service-info h3 {
            color: #FFFFFF;
        }

        .feature-card p,
        .service-info p {
            color: #C9DBF6;
        }

        .feature-icon {
            color: #7CEBFF;
            background: rgba(0, 200, 255, 0.14);
            border: 1px solid rgba(0, 200, 255, 0.30);
        }

        .feature-card:hover .feature-icon {
            color: #04162B;
            background: #63E8FF;
        }

        .service-img {
            color: #74E9FF;
            background: linear-gradient(135deg, rgba(0, 137, 220, 0.32), rgba(255, 176, 0, 0.20));
        }

        .carousel-container {
            filter: drop-shadow(0 18px 30px rgba(0, 0, 0, 0.24));
        }

        .carousel-slide {
            color: #FFFFFF;
            background: rgba(5, 22, 53, 0.90);
            border-color: rgba(93, 188, 255, 0.30);
            backdrop-filter: blur(14px);
        }

        .carousel-slide p.lead {
            color: #FFFFFF;
        }

        .carousel-slide p.desc {
            color: #C9DBF6;
        }

        .carousel-slide .badge {
            color: #9CEFFF;
            background: rgba(0, 200, 255, 0.15);
            border: 1px solid rgba(0, 200, 255, 0.25);
        }

        .carousel-slide small {
            color: #A9C2E5;
        }

        .carousel-btn {
            color: #EAF8FF;
            background: rgba(4, 22, 53, 0.94);
            border-color: rgba(91, 202, 255, 0.45);
        }

        .carousel-btn:hover {
            color: #031426;
            background: #63E8FF;
            border-color: #63E8FF;
        }

        .dot {
            background: rgba(210, 232, 255, 0.48);
        }

        .dot.active {
            background: #FFC247;
        }

        footer {
            background: rgba(1, 7, 23, 0.94);
            color: #D4E4FF;
            border-top: 1px solid rgba(91, 202, 255, 0.22);
        }

        .footer-tagline,
        .footer-links span,
        .copyright {
            color: #A9BEDD;
        }

        .footer-links span i {
            color: #FFC247;
        }

        .app-banner {
            background: linear-gradient(110deg, rgba(1, 10, 31, 0.96), rgba(0, 76, 125, 0.94));
            border-top: 1px solid rgba(91, 202, 255, 0.25);
            border-bottom: 1px solid rgba(91, 202, 255, 0.25);
        }

        .app-banner strong {
            color: #FFC247;
        }

        @media (max-width: 768px) {
            body {
                background-attachment: scroll;
            }
        }

    </style>
</head>

<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-95VNH7KVRB"></script>
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag() { dataLayer.push(arguments); }
    gtag('js', new Date());
    gtag('config', 'G-95VNH7KVRB');
</script>

<body>

    <!-- ============================================== -->
    <!-- NAV -->
    <!-- ============================================== -->
    <nav>
        <div class="logo">
            <img src="logo/logo.jpeg"
                alt="Villaruz Print Shop Logo">
        </div>
        <a href="role.php" class="btn-order">
            <i class="fas fa-user-circle"></i> Select Role
        </a>
    </nav>

    <!-- ============================================== -->
    <!-- HERO -->
    <!-- ============================================== -->
    <section class="hero">
        <div class="hero-inner">
            <div class="hero-eyebrow">
                <i class="fas fa-star"></i> Trusted by DepEd, Local Businesses & Families
            </div>

            <h1 class="hero-title">
                <span class="gradient">Villaruz Print Shop</span><br>
                &amp; General Merchandise
            </h1>

            <p class="hero-desc">
                Premium printing and everyday essentials — banners, flyers, custom merch,
                school and office supplies. Fast, reliable, and creatively driven.
            </p>

            <div class="hero-buttons">
                <a href="role.php" class="btn-primary">
                    <i class="fas fa-rocket"></i> Get Started
                </a>
            </div>
        </div>
    </section>

    <!-- ============================================== -->
    <!-- FEATURES -->
    <!-- ============================================== -->
    <section class="features">
        <div class="section-head">
            <div class="section-tag">Why Choose Us</div>
            <h2 class="section-title">Built on <span>Quality</span></h2>
            <p class="section-sub">Where quality meets convenience — trusted by DepEd, Local Businesses & Families</p>
        </div>

        <div class="feature-grid">
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-palette"></i></div>
                <h3>Custom Design Studio</h3>
                <p>In-house experts bring your vision to life. Free mockups and revisions on bulk orders.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-cubes"></i></div>
                <h3>General Merchandise Hub</h3>
                <p>From notebooks and ballpens to home tools — one-stop daily essentials.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-leaf"></i></div>
                <h3>Eco-Conscious Materials</h3>
                <p>Sustainable paper, soy-based inks, and reusable banners for green printing.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-truck-fast"></i></div>
                <h3>Express Turnaround</h3>
                <p>Same-day rush prints and scheduled delivery across the metro.</p>
            </div>
        </div>
    </section>

    <!-- ============================================== -->
    <!-- CAROUSEL -->
    <!-- ============================================== -->
    <section class="carousel-section">
        <div class="section-head">
            <div class="section-tag">Our Promise</div>
            <h2 class="section-title">Commitment to <span>Excellence</span></h2>
            <p class="section-sub">What makes Villaruz your go-to print and retail partner</p>
        </div>

        <div class="carousel-container">
            <div class="carousel-slides">
                <div class="carousel-slide">
                    <div class="slide-icon"><i class="fas fa-print"></i></div>
                    <p class="lead">Premium Digital &amp; Offset Printing</p>
                    <p class="desc">Vibrant color accuracy, high-resolution output for all marketing materials.</p>
                    <div class="badge">Pro-Grade Equipment</div>
                    <small>Consistent quality, batch after batch</small>
                </div>

                <div class="carousel-slide">
                    <div class="slide-icon"><i class="fas fa-box-open"></i></div>
                    <p class="lead">General Merchandise Corner</p>
                    <p class="desc">School supplies, office needs, cleaning tools — real value items.</p>
                    <div class="badge">Everyday Low Prices</div>
                    <small>Retail + Wholesale availability</small>
                </div>

                <div class="carousel-slide">
                    <div class="slide-icon"><i class="fas fa-stopwatch"></i></div>
                    <p class="lead">Rush Orders Ready in Hours</p>
                    <p class="desc">Need 500 flyers for tomorrow's event? We deliver express printing.</p>
                    <div class="badge">Same-Day Service</div>
                    <small>24-hour hotline for urgent requests</small>
                </div>

                <div class="carousel-slide">
                    <div class="slide-icon"><i class="fas fa-hand-holding-heart"></i></div>
                    <p class="lead">Community &amp; B2B Partnerships</p>
                    <p class="desc">Loyalty programs, bulk discounts, and flexible terms for regulars.</p>
                    <div class="badge">Corporate Friendly</div>
                    <small>Trusted by 200+ local businesses</small>
                </div>
            </div>

            <button class="carousel-btn prev" onclick="prevSlide()" aria-label="Previous slide">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button class="carousel-btn next" onclick="nextSlide()" aria-label="Next slide">
                <i class="fas fa-chevron-right"></i>
            </button>

            <div class="carousel-dots" id="dots"></div>
        </div>
    </section>

    <!-- ============================================== -->
    <!-- SERVICES -->
    <!-- ============================================== -->
    <section class="services" id="services">
        <div class="section-head">
            <div class="section-tag">What We Offer</div>
            <h2 class="section-title">Our <span>Services</span></h2>
            <p class="section-sub">Printing and merchandise — everything under one roof</p>
        </div>

        <div class="services-grid">
            <div class="service-card">
                <div class="service-img"><i class="fas fa-tshirt"></i></div>
                <div class="service-info">
                    <h3>Custom Apparel &amp; Totes</h3>
                    <p>Screenprint, DTG, embroidery. Perfect for teams and giveaways.</p>
                </div>
            </div>

            <div class="service-card">
                <div class="service-img"><i class="fas fa-book"></i></div>
                <div class="service-info">
                    <h3>School &amp; Office Supplies</h3>
                    <p>Notebooks, folders, record books, calculators, and more.</p>
                </div>
            </div>

            <div class="service-card">
                <div class="service-img"><i class="fas fa-store"></i></div>
                <div class="service-info">
                    <h3>Household Essentials</h3>
                    <p>Cleaning, storage, kitchen basics — quality general merchandise.</p>
                </div>
            </div>

            <div class="service-card">
                <div class="service-img"><i class="fas fa-flag-checkered"></i></div>
                <div class="service-info">
                    <h3>Large Format &amp; Banners</h3>
                    <p>Tarpaulins, posters, retractable banners, outdoor-grade prints.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================== -->
    <!-- FOOTER -->
    <!-- ============================================== -->
    <footer>
        <div class="footer-inner">
            <div class="footer-brand">Villaruz Print Shop &amp; General Merchandise</div>
            <div class="footer-tagline">Printing excellence, delivered daily.</div>

            <div class="footer-links">
                <span><i class="fas fa-print"></i> Custom Printing</span>
                <span class="footer-sep">•</span>
                <span><i class="fas fa-store"></i> Retail Merchandise</span>
                <span class="footer-sep">•</span>
                <span><i class="fas fa-headset"></i> Support 24/7</span>
                <span class="footer-sep">•</span>
                <span><i class="fas fa-envelope"></i> villaruzsofiaapp@gmail.com</span>
            </div>

            <div class="copyright">
                © 2026 Villaruz Print Shop &amp; Gen. MDSE. All rights reserved.
            </div>
        </div>
    </footer>

    <script>
        // ==============================================
        // CAROUSEL
        // ==============================================
        let currentSlide = 0;
        const slides = document.querySelectorAll('.carousel-slide');
        const slidesContainer = document.querySelector('.carousel-slides');
        const dotsContainer = document.getElementById('dots');
        let autoPlayInterval;

        function updateCarousel() {
            if (slidesContainer) {
                slidesContainer.style.transform = `translateX(-${currentSlide * 100}%)`;
            }
            document.querySelectorAll('.dot').forEach((dot, idx) => {
                dot.classList.toggle('active', idx === currentSlide);
            });
        }

        function createDots() {
            if (!dotsContainer) return;
            slides.forEach((_, i) => {
                const dot = document.createElement('div');
                dot.classList.add('dot');
                if (i === 0) dot.classList.add('active');
                dot.addEventListener('click', () => {
                    currentSlide = i;
                    updateCarousel();
                    resetAutoPlay();
                });
                dotsContainer.appendChild(dot);
            });
        }

        function nextSlide() {
            currentSlide = (currentSlide + 1) % slides.length;
            updateCarousel();
            resetAutoPlay();
        }

        function prevSlide() {
            currentSlide = (currentSlide - 1 + slides.length) % slides.length;
            updateCarousel();
            resetAutoPlay();
        }

        function startAutoPlay() {
            autoPlayInterval = setInterval(() => nextSlide(), 5200);
        }

        function resetAutoPlay() {
            clearInterval(autoPlayInterval);
            startAutoPlay();
        }

        if (slides.length) {
            createDots();
            startAutoPlay();
        }

        // ==============================================
        // FINGERPRINT DETECTION (For App Users)
        // ==============================================
        if (typeof AndroidBiometric !== 'undefined') {
            var downloadBtns = document.querySelectorAll('.btn-download, #downloadAppBtn, .btn-sm');
            downloadBtns.forEach(function (btn) {
                btn.innerHTML = '<i class="fas fa-fingerprint"></i> Login with Fingerprint';
                btn.href = '#';
                btn.onclick = function (e) {
                    e.preventDefault();
                    if (typeof AndroidBiometric !== 'undefined') {
                        var challenge = "app_launch_" + Date.now();
                        AndroidBiometric.authenticate(challenge);
                    }
                };
            });

            var appBanner = document.getElementById('appBanner');
            if (appBanner) {
                appBanner.style.display = 'none';
            }
        }

        // ==============================================
        // FINGERPRINT CALLBACKS
        // ==============================================
        function biometricSuccess(challenge) {
            window.location.href = 'faceLogin.php';
        }

        function biometricFailed() {
            alert('Fingerprint not recognized. Please try again.');
        }

        function cancelBiometric() {
            console.log('Fingerprint login cancelled.');
        }

        function biometricError(errorMessage) {
            alert('Biometric error: ' + errorMessage);
        }
    </script>
</body>

</html>