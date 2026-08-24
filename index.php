<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Villaruz Print Shop & General Merchandise | Modern Print & Retail</title>
    <!-- Font Awesome 6 (free) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts: Poppins + Inter for modern look -->
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,600;14..32,700;14..32,800&family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Poppins', sans-serif;
            background-color: #FCF9F5;
            /* soft warm paper tone */
            color: #1E2A2F;
            line-height: 1.5;
            scroll-behavior: smooth;
        }

        /* Modern print-shop color scheme: deep ink blue, rich ochre accents, clean whites, slate gray */
        :root {
            --ink-deep: #1C2E36;
            --press-cyan: #2C5F6E;
            --gold-accent: #D9A13B;
            --warm-paper: #FCF9F5;
            --slate-mist: #EFF3F0;
            --card-bg: #FFFFFF;
            --border-light: #E2E8F0;
            --text-dark: #1E2A2F;
            --text-muted: #4A5B66;
            --success-green: #2D6A4F;
            --shadow-sm: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.02);
            --shadow-md: 0 20px 25px -12px rgba(0, 0, 0, 0.08);
        }

        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #E2E8F0;
        }

        ::-webkit-scrollbar-thumb {
            background: #2C5F6E;
            border-radius: 12px;
        }

        /* Navigation - modern glassmorphism effect */
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 5%;
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid rgba(44, 95, 110, 0.15);
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.02);
        }

        .logo img {
            width: 110px;
            height: auto;
            max-width: 100%;
            object-fit: contain;
            display: block;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.05));
        }

        .nav-links {
            display: flex;
            gap: 32px;
        }

        .nav-links a {
            color: #1C2E36;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: 0.2s;
            letter-spacing: -0.2px;
        }

        .nav-links a:hover {
            color: #D9A13B;
        }

        .btn-order {
            background: #2C5F6E;
            border: none;
            padding: 10px 28px;
            border-radius: 40px;
            font-weight: 700;
            color: white;
            cursor: pointer;
            transition: 0.25s;
            text-decoration: none;
            font-size: 0.9rem;
            display: inline-block;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            letter-spacing: -0.2px;
        }

        .btn-order:hover {
            background: #1C3E48;
            transform: translateY(-2px);
            box-shadow: 0 10px 18px -6px rgba(44, 95, 110, 0.3);
        }

        /* Hero Section — modern minimal */
        .hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 60px 5%;
            gap: 40px;
            background: linear-gradient(135deg, #FCF9F5 0%, #F6F3EF 100%);
        }

        .hero-content {
            flex: 1;
        }

        .hero-title {
            font-size: clamp(32px, 5vw, 56px);
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 20px;
            color: #1C2E36;
        }

        .hero-title span {
            background: linear-gradient(120deg, #2C5F6E, #D9A13B);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
            border-bottom: none;
        }

        .hero-desc {
            font-size: 1.05rem;
            color: #3A4F5A;
            margin-bottom: 32px;
            max-width: 550px;
        }

        .hero-buttons {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .btn-primary {
            background: #2C5F6E;
            border: none;
            padding: 12px 32px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1rem;
            color: white;
            cursor: pointer;
            transition: 0.2s;
            box-shadow: var(--shadow-sm);
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary:hover {
            background: #1E454F;
            transform: translateY(-3px);
            box-shadow: 0 12px 20px -12px rgba(44, 95, 110, 0.4);
        }

        .btn-secondary {
            background: transparent;
            border: 1.5px solid #2C5F6E;
            padding: 12px 28px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            color: #1C2E36;
            cursor: pointer;
            transition: 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-secondary:hover {
            background: rgba(44, 95, 110, 0.06);
            border-color: #D9A13B;
            color: #D9A13B;
            transform: translateY(-2px);
        }

        /* Features Section */
        .features {
            padding: 70px 5%;
            background: #FFFFFF;
        }

        .section-title {
            text-align: center;
            font-size: clamp(28px, 5vw, 40px);
            font-weight: 800;
            margin-bottom: 12px;
            color: #1C2E36;
        }

        .section-title span {
            color: #D9A13B;
        }

        .section-sub {
            text-align: center;
            color: #5A6E7A;
            margin-bottom: 48px;
            font-size: 1rem;
            max-width: 680px;
            margin-left: auto;
            margin-right: auto;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 30px;
        }

        .feature-card {
            background: var(--card-bg);
            padding: 32px 24px;
            border-radius: 28px;
            text-align: center;
            border: 1px solid #EDF2F7;
            transition: all 0.25s ease;
            box-shadow: var(--shadow-sm);
        }

        .feature-card:hover {
            border-color: #D9A13B;
            transform: translateY(-6px);
            box-shadow: var(--shadow-md);
        }

        .feature-icon {
            font-size: 46px;
            color: #2C5F6E;
            margin-bottom: 20px;
        }

        .feature-card h3 {
            font-size: 1.4rem;
            margin-bottom: 12px;
            color: #1C2E36;
        }

        .feature-card p {
            color: #4F6F7C;
            font-size: 0.9rem;
        }

        /* Carousel - fresh modern */
        .carousel-section {
            padding: 70px 5%;
            background: #F8F6F2;
        }

        .carousel-container {
            max-width: 950px;
            margin: 0 auto;
            position: relative;
            overflow: hidden;
            border-radius: 32px;
        }

        .carousel-slides {
            display: flex;
            transition: transform 0.5s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        }

        .carousel-slide {
            min-width: 100%;
            padding: 44px 32px;
            background: white;
            border-radius: 32px;
            text-align: center;
            box-shadow: var(--shadow-md);
            border: 1px solid #EEF2F6;
        }

        .carousel-slide i {
            font-size: 52px;
            color: #D9A13B;
            margin-bottom: 20px;
        }

        .carousel-slide p {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 12px;
            color: #1C2E36;
        }

        .carousel-slide .badge {
            display: inline-block;
            background: #2C5F6E10;
            padding: 5px 18px;
            border-radius: 60px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #2C5F6E;
            margin-top: 12px;
        }

        .carousel-slide small {
            display: block;
            color: #7A8E9B;
            font-size: 0.8rem;
            margin-top: 16px;
        }

        .carousel-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: white;
            border: 1px solid #E2E8F0;
            width: 40px;
            height: 40px;
            border-radius: 60px;
            cursor: pointer;
            font-size: 1rem;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            color: #1C2E36;
        }

        .carousel-btn:hover {
            background: #2C5F6E;
            color: white;
            border-color: #2C5F6E;
        }

        .carousel-btn.prev {
            left: 16px;
        }

        .carousel-btn.next {
            right: 16px;
        }

        .carousel-dots {
            text-align: center;
            margin-top: 24px;
        }

        .dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #Cbd5E0;
            margin: 0 6px;
            cursor: pointer;
            transition: 0.2s;
        }

        .dot.active {
            background: #D9A13B;
            width: 26px;
            border-radius: 10px;
        }

        /* Services grid */
        .services {
            padding: 70px 5%;
            background: #FFFFFF;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 28px;
            margin-top: 20px;
        }

        .service-card {
            background: white;
            border-radius: 28px;
            overflow: hidden;
            border: 1px solid #EDF2F7;
            transition: 0.25s;
            cursor: pointer;
            text-align: center;
            box-shadow: var(--shadow-sm);
        }

        .service-card:hover {
            border-color: #D9A13B;
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .service-img {
            height: 130px;
            background: #F1F5F9;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 54px;
            color: #2C5F6E;
        }

        .service-info {
            padding: 22px 18px 24px;
        }

        .service-info h3 {
            font-size: 1.3rem;
            margin-bottom: 8px;
            color: #1C2E36;
        }

        .service-info p {
            color: #5A6F7C;
            font-size: 0.85rem;
        }



        .btn-pricing {
            width: 100%;
            background: #2C5F6E;
            border: none;
            padding: 14px;
            border-radius: 60px;
            font-weight: 700;
            color: white;
            cursor: pointer;
            transition: 0.2s;
            text-align: center;
            display: inline-block;
            font-size: 0.9rem;
        }

        .btn-pricing:hover {
            background: #1E454F;
        }

        .btn-outline {
            background: transparent;
            border: 1.5px solid #2C5F6E;
            color: #1C2E36;
        }

        .btn-outline:hover {
            background: #F0F5F8;
            border-color: #D9A13B;
            color: #D9A13B;
        }

        /* Footer */
        footer {
            padding: 40px 5% 28px;
            background: #1C2E36;
            color: #CFDFE8;
            border-top: 1px solid #2C4853;
        }

        .copyright {
            text-align: center;
            font-size: 0.8rem;
            opacity: 0.85;
        }

        .copyright p i {
            margin: 0 4px;
            color: #D9A13B;
        }

        /* Responsive */
        @media (max-width: 768px) {
            nav {
                padding: 12px 5%;
            }

            .logo img {
                width: 85px;
            }

            .nav-links {
                display: none;
            }

            .btn-order {
                padding: 8px 20px;
                font-size: 0.8rem;
            }

            .hero {
                flex-direction: column;
                text-align: center;
                padding: 48px 5%;
            }

            .hero-desc {
                margin-left: auto;
                margin-right: auto;
            }

            .hero-buttons {
                justify-content: center;
            }

            .feature-grid {
                grid-template-columns: 1fr;
            }

            .services-grid {
                grid-template-columns: 1fr;
            }

            .pricing-grid {
                flex-direction: column;
                align-items: center;
            }

            .pricing-card.featured {
                transform: scale(1);
            }

            .carousel-btn {
                width: 34px;
                height: 34px;
            }
        }

        @media (max-width: 480px) {
            .hero-title {
                font-size: 2rem;
            }

            .btn-primary,
            .btn-secondary {
                font-size: 0.85rem;
                padding: 10px 20px;
            }

            .feature-card {
                padding: 24px 16px;
            }

            .carousel-slide {
                padding: 32px 20px;
            }
        }
    </style>
</head>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-95VNH7KVRB"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-95VNH7KVRB');
</script>
<body>
    <nav>
        <div class="logo">
            <img src="logo/logo.jpeg" alt="Villaruz Print Shop Logo">
        </div>
        <div class="nav-links">
            <a href="#">Home</a>
            <a href="#services">Services</a>
            <a href="#pricing">Packages</a>
        </div>
        <a href="login.php" class="btn-order">Login</a>
    </nav>

    <section class="hero">
        <div class="hero-content">
            <h1 class="hero-title"><span>Villaruz Print Shop</span> & General Merchandise</h1>
            <p class="hero-desc">Premium printing + everyday essentials — banners, flyers, custom merch, school & office
                supplies. Fast, reliable, and creatively driven.</p>
            <div class="hero-buttons">
                <a href="registration.php" class="btn-primary">Create an account</a>
                <a href="#" class="btn-secondary">Scroll to Explore Service</a>
            </div>
        </div>
    </section>

    <!-- Features -->
    <section class="features">
        <h2 class="section-title">Why Choose <span>Villaruz</span></h2>
        <p class="section-sub">Where quality meets convenience — trusted by local businesses and families</p>
        <div class="feature-grid">
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-palette"></i></div>
                <h3>Custom Design Studio</h3>
                <p>In-house experts bring your vision to life. Free mockups & revisions on bulk orders.</p>
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

    <!-- Carousel -->
    <section class="carousel-section">
        <h2 class="section-title">Commitment to <span>Excellence</span></h2>
        <p class="section-sub">What makes Villaruz your go-to print & retail partner</p>
        <div class="carousel-container">
            <div class="carousel-slides">
                <div class="carousel-slide">
                    <i class="fas fa-print"></i>
                    <p>🎨 Premium Digital & Offset Printing</p>
                    <p>Vibrant color accuracy, high-resolution output for all marketing materials.</p>
                    <div class="badge">Pro-Grade Equipment</div>
                    <small>Consistent quality, batch after batch</small>
                </div>
                <div class="carousel-slide">
                    <i class="fas fa-box-open"></i>
                    <p>📦 General Merchandise Corner</p>
                    <p>School supplies, office needs, cleaning tools — real value items.</p>
                    <div class="badge">Everyday Low Prices</div>
                    <small>Retail + Wholesale availability</small>
                </div>
                <div class="carousel-slide">
                    <i class="fas fa-stopwatch"></i>
                    <p>⏱️ Rush Orders Ready in Hours</p>
                    <p>Need 500 flyers for tomorrow's event? We deliver express printing.</p>
                    <div class="badge">Same-Day Service</div>
                    <small>24-hour hotline for urgent requests</small>
                </div>
                <div class="carousel-slide">
                    <i class="fas fa-hand-holding-heart"></i>
                    <p>🤝 Community & B2B Partnerships</p>
                    <p>Loyalty programs, bulk discounts, and flexible terms for regulars.</p>
                    <div class="badge">Corporate Friendly</div>
                    <small>Trusted by 200+ local businesses</small>
                </div>
            </div>
            <button class="carousel-btn prev" onclick="prevSlide()">❮</button>
            <button class="carousel-btn next" onclick="nextSlide()">❯</button>
            <div class="carousel-dots" id="dots"></div>
        </div>
    </section>

    <!-- Services -->
    <section class="services" id="services">
        <h2 class="section-title">Our <span>Offerings</span></h2>
        <p class="section-sub">Printing + merchandise — everything under one roof</p>
        <div class="services-grid">
            <div class="service-card">
                <div class="service-img"><i class="fas fa-tshirt"></i></div>
                <div class="service-info">
                    <h3>Custom Apparel & Totes</h3>
                    <p>Screenprint, DTG, embroidery. Perfect for teams & giveaways.</p>
                </div>
            </div>
            <div class="service-card">
                <div class="service-img"><i class="fas fa-book"></i></div>
                <div class="service-info">
                    <h3>School & Office Supplies</h3>
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
                    <h3>Large Format & Banners</h3>
                    <p>Tarpaulins, posters, retractable banners, outdoor-grade prints.</p>
                </div>
            </div>
            <!-- <a href="app.php" style="text-decoration: none; color: inherit;">
                <div class="service-card">
                    <div class="service-img">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="service-info">
                        <h3>Do Task & Earn</h3>
                        <p>Complete simple tasks: Data Entry, Math Solving, Reverse Word, Count Item</p>
                        <span class="badge">Earn up to ₱0.01 per task</span>
                    </div>
                </div>
            </a> -->
        </div>
    </section>


    <footer>
        <div class="copyright">
            <p>© 2026 Villaruz Print Shop & GEN. MDSE.</p>
            <p style="margin-top: 12px;">
                <i class="fas fa-print"></i> Custom Printing |
                <i class="fas fa-store"></i> Retail Merchandise |
                <i class="fas fa-headset"></i> Support 24/7 |
                <i class="fas fa-code"></i> Developer: Norlie Jay Verzosa |
                <i class="fas fa-envelope"></i> Message Us: villaruzprintshop@gmail.com
            </p>
        </div>
    </footer>

    <script>
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
    </script>
</body>

</html>