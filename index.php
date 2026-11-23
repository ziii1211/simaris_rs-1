<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIMARIS - RS Bhayangkara</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8fafc;
            margin: 0;
            padding: 0;
            color: #333;
            overflow-x: hidden;
        }

        /* ===== NAVBAR LEBAR, MEWAH & TURUN JAUH LEBIH KE BAWAH ===== */
        .navbar {
            background: linear-gradient(90deg, #4e73df, #1cc88a);
            padding: 40px 70px;
            min-height: 135px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            color: #fff !important;
            font-weight: 800;
            font-size: 32px;
            letter-spacing: 0.8px;
            text-shadow: 0 2px 6px rgba(0, 0, 0, 0.18);
        }

        .navbar-brand img {
            width: 72px;
            height: 72px;
            margin-right: 22px;
            border-radius: 50%;
            border: 3.5px solid rgba(255, 255, 255, 0.95);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
        }

        /* TOMBOL LOGIN JAUH LEBIH BESAR, TEBAL & JELAS */
        .btn-login {
            background: #ffffff;
            color: #1a3c8b;
            border-radius: 50px;
            padding: 18px 52px;
            font-weight: 800;
            font-size: 22px;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.2);
            display: inline-flex;
            align-items: center;
            letter-spacing: 0.5px;
        }

        .btn-login:hover {
            background: #f8fafc;
            color: #2563eb;
            transform: translateY(-3px);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.28);
        }

        /* ===== HERO SECTION (FULL & PROPORTIONAL) ===== */
        .hero-section {
            position: relative;
            text-align: center;
            padding: 45px 20px 45px;
            background: linear-gradient(180deg, #edf4ff 0%, #f8fafc 100%);
            overflow: hidden;
            min-height: calc(100vh - 135px);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .hero-bg-svg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 0;
            opacity: 0.12;
            pointer-events: none;
        }

        .hero-section svg.text-svg {
            position: absolute;
            top: 28%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 96%;
            max-width: 1600px;
            height: auto;
            opacity: 0.05;
            z-index: 0;
            pointer-events: none;
        }

        .hero-content {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 1240px;
            margin: 0 auto;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(78, 115, 223, 0.12);
            color: #1e3a8a;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 1.6px;
            text-transform: uppercase;
            padding: 7px 24px;
            border-radius: 50px;
            margin-bottom: 12px;
            border: 1px solid rgba(78, 115, 223, 0.25);
            box-shadow: 0 4px 12px rgba(78, 115, 223, 0.08);
        }

        /* TULISAN SIMARIS BESAR & GAGAH - TIDAK TENGGELAM */
        .hero-section h1 {
            color: #1a3c8b;
            font-weight: 900;
            font-size: clamp(3.2rem, 5.5vw, 4.6rem);
            letter-spacing: 2px;
            margin-bottom: 8px;
            line-height: 1.15;
            text-shadow: 0 2px 12px rgba(26, 60, 139, 0.08);
        }

        .hero-section h4 {
            color: #2563eb;
            font-weight: 700;
            font-size: clamp(1.3rem, 2vw, 1.75rem);
            margin-bottom: 14px;
            line-height: 1.35;
        }

        .hero-section p.hero-desc {
            color: #475569;
            font-size: clamp(1.02rem, 1.2vw, 1.18rem);
            max-width: 860px;
            margin: 0 auto 30px;
            line-height: 1.7;
        }

        /* ===== FEATURE CARDS DI DALAM HERO ===== */
        .card-section {
            display: flex;
            justify-content: center;
            align-items: stretch;
            flex-wrap: wrap;
            gap: 24px;
            margin-top: 5px;
            position: relative;
            z-index: 1;
        }

        .feature-card {
            background: #ffffff;
            border-radius: 18px;
            padding: 26px 22px;
            flex: 1 1 300px;
            max-width: 360px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(30, 41, 59, 0.06);
            border: 1px solid rgba(226, 232, 240, 0.85);
            transition: all 0.35s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 18px 35px rgba(26, 60, 139, 0.12);
            border-color: #cbd5e1;
        }

        .feature-card img {
            width: 58px;
            height: 58px;
            margin-bottom: 14px;
            transition: transform 0.3s ease;
        }

        .feature-card:hover img {
            transform: scale(1.08);
        }

        .feature-card .feature-tag {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 3px 10px;
            border-radius: 20px;
            margin-bottom: 10px;
            display: inline-block;
        }

        .ftag-infra { background: #eef2ff; color: #4e73df; }
        .ftag-aset { background: #e6f9f2; color: #1cc88a; }
        .ftag-lapor { background: #fef8eb; color: #d97706; }

        .feature-card h5 {
            color: #1a3c8b;
            font-weight: 700;
            font-size: 1.25rem;
            margin-bottom: 8px;
        }

        .feature-card p {
            color: #64748b;
            font-size: 14px;
            line-height: 1.55;
            margin: 0;
        }

        /* ===== KONTAK & GOOGLE MAPS SECTION (LEBAR, BESAR & TEGAS) ===== */
        .contact-section {
            background: #ffffff;
            padding: 75px 20px 80px;
            border-top: 1px solid #e2e8f0;
        }

        .contact-container {
            max-width: 1360px;
            margin: 0 auto;
        }

        .section-header {
            text-align: center;
            margin-bottom: 42px;
        }

        .section-header h2 {
            color: #1a3c8b;
            font-weight: 900;
            font-size: clamp(2.3rem, 3.4vw, 3rem);
            margin-bottom: 8px;
            letter-spacing: 1.5px;
        }

        .section-header p {
            color: #64748b;
            font-size: 1.15rem;
            max-width: 700px;
            margin: 0 auto;
        }

        .contact-box {
            background: #f8fafc;
            border-radius: 20px;
            padding: 24px 28px;
            border: 1px solid #eef2f6;
            box-shadow: 0 6px 20px rgba(30, 41, 59, 0.04);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 22px;
            transition: all 0.3s ease;
        }

        .contact-box:hover {
            background: #ffffff;
            transform: translateY(-3px);
            box-shadow: 0 14px 28px rgba(30, 41, 59, 0.09);
            border-color: #cbd5e1;
        }

        .contact-box-icon {
            width: 66px;
            height: 66px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: #ffffff;
            flex-shrink: 0;
        }

        .c-icon-blue {
            background: linear-gradient(135deg, #4e73df, #224abe);
            box-shadow: 0 8px 18px rgba(78, 115, 223, 0.35);
        }

        .c-icon-green {
            background: linear-gradient(135deg, #1cc88a, #13855c);
            box-shadow: 0 8px 18px rgba(28, 200, 138, 0.35);
        }

        .c-icon-amber {
            background: linear-gradient(135deg, #f6c23e, #df9b06);
            box-shadow: 0 8px 18px rgba(246, 194, 62, 0.35);
        }

        .c-icon-cyan {
            background: linear-gradient(135deg, #36b9cc, #1a8997);
            box-shadow: 0 8px 18px rgba(54, 185, 204, 0.35);
        }

        .contact-box-info {
            flex-grow: 1;
        }

        .contact-box-label {
            font-size: 13px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }

        .contact-box-val {
            font-size: 17.5px;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.5;
            margin-bottom: 6px;
        }

        .contact-box-val.tel-big {
            font-size: 23px;
            font-weight: 800;
            color: #10b981;
            letter-spacing: 0.5px;
        }

        .contact-box-val.email-val {
            font-size: 19px;
            font-weight: 700;
            color: #d97706;
        }

        .contact-box-val.web-val {
            font-size: 18px;
            font-weight: 700;
            color: #0284c7;
        }

        .contact-box-link {
            font-size: 14.5px;
            font-weight: 600;
            color: #4e73df;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: color 0.2s ease;
        }

        .contact-box-link:hover {
            color: #224abe;
            text-decoration: underline;
        }

        /* Peta Google Maps */
        .map-wrapper {
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 25px rgba(30, 41, 59, 0.06);
            overflow: hidden;
            height: 100%;
            min-height: 550px;
            display: flex;
            flex-direction: column;
        }

        .map-header-bar {
            padding: 18px 24px;
            background: #ffffff;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .map-header-bar .map-title {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pulse-dot {
            width: 12px;
            height: 12px;
            background-color: #10b981;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            animation: pulse-green 2s infinite;
        }

        @keyframes pulse-green {
            0% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            }
            70% {
                transform: scale(1);
                box-shadow: 0 0 0 8px rgba(16, 185, 129, 0);
            }
            100% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
            }
        }

        .map-frame {
            flex-grow: 1;
            width: 100%;
            min-height: 480px;
            border: 0;
        }

        /* ===== FOOTER ===== */
        footer {
            background-color: #f8fafc;
            text-align: center;
            padding: 24px 20px;
            font-size: 14px;
            color: #64748b;
            border-top: 1px solid #e5e7eb;
        }

        footer strong {
            color: #1a3c8b;
        }

        @media (max-width: 768px) {
            .navbar {
                padding: 16px 20px;
            }

            .contact-box {
                flex-direction: column;
                align-items: flex-start;
                gap: 14px;
            }

            .map-wrapper {
                min-height: 350px;
                margin-top: 20px;
            }

            .hero-section svg.text-svg {
                width: 130%;
            }
        }
    </style>
</head>

<body>
    <!-- NAVBAR (LEBIH TEBAL & TOMBOL LOGIN BESAR) -->
    <nav class="navbar d-flex justify-content-between align-items-center">
        <a class="navbar-brand" href="#">
            <img src="assets/img/logo.png" alt="Logo RS Bhayangkara">
            SIMARIS RS Bhayangkara
        </a>
        <a href="login.php" class="btn btn-login">
            <i class="bi bi-box-arrow-in-right me-2 fs-4"></i> Login
        </a>
    </nav>

    <!-- HERO (FULL SCREEN DENGAN TULISAN BESAR & GAGAH + 3 KARTU FITUR) -->
    <section class="hero-section">
        <svg class="hero-bg-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 800">
            <polygon fill="#1a73e8" points="0,800 1440,0 1440,200 0,1000" />
            <polygon fill="#00b4d8" points="0,400 1440,100 1440,300 0,900" />
        </svg>

        <svg class="text-svg" viewBox="0 0 1500 240">
            <defs>
                <linearGradient id="grad" x1="0" x2="1" y1="0" y2="1">
                    <stop offset="0%" stop-color="#4e73df" />
                    <stop offset="100%" stop-color="#1cc88a" />
                </linearGradient>
            </defs>
            <text x="50%" y="50%" text-anchor="middle" dominant-baseline="central" fill="url(#grad)" font-size="200" font-weight="900" letter-spacing="22"
                font-family="'Poppins', sans-serif">SIMARIS</text>
        </svg>

        <div class="hero-content">
            <div class="hero-badge" data-aos="fade-down">
                <i class="bi bi-hospital"></i> RS BHAYANGKARA TK. III BANJARMASIN
            </div>
            <h1>SIMARIS</h1>
            <h4>Sistem Informasi Manajemen Infrastruktur dan Aset Rumah Sakit</h4>
            <p class="hero-desc">
                Selamat datang di portal <strong>SIMARIS</strong> RS Bhayangkara Banjarmasin.<br>
                Akses dan pemantauan data infrastruktur, aset medis & non-medis, pemeliharaan ruangan, serta pelaporan otomatis secara cepat, aman, dan efisien.
            </p>

            <!-- 3 KARTU FITUR DI DALAM HERO -->
            <div class="card-section container" data-aos="fade-up">
                <div class="feature-card" data-aos="zoom-in" data-aos-delay="100">
                    <img src="https://cdn-icons-png.flaticon.com/512/1046/1046857.png" alt="Infrastruktur Icon">
                    <span class="feature-tag ftag-infra">Fasilitas & Gedung</span>
                    <h5>Infrastruktur</h5>
                    <p>Kelola data ruangan, gedung, dan infrastruktur rumah sakit secara cepat dan efisien.</p>
                </div>
                <div class="feature-card" data-aos="zoom-in" data-aos-delay="200">
                    <img src="https://cdn-icons-png.flaticon.com/512/2921/2921222.png" alt="Aset Icon">
                    <span class="feature-tag ftag-aset">Inventaris & QR Code</span>
                    <h5>Aset</h5>
                    <p>Pantau status kondisi fisik, nilai aset, dan kontrol seluruh aset RS Bhayangkara secara terintegrasi.</p>
                </div>
                <div class="feature-card" data-aos="zoom-in" data-aos-delay="300">
                    <img src="https://cdn-icons-png.flaticon.com/512/3176/3176366.png" alt="Laporan Icon">
                    <span class="feature-tag ftag-lapor">Rekap & Analisis</span>
                    <h5>Laporan</h5>
                    <p>Hasilkan laporan otomatis, kartu inventaris (KIR), dan ekspor data yang selalu diperbarui.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- KONTAK & GOOGLE MAPS (LEBAR, BESAR & TEGAS) -->
    <section class="contact-section">
        <div class="container-fluid contact-container">
            <div class="section-header" data-aos="fade-up">
                <h2>KONTAK</h2>
                <p>Informasi kontak resmi dan petunjuk rute lokasi RS Bhayangkara Tk. III Banjarmasin</p>
            </div>

            <div class="row g-4" data-aos="fade-up" data-aos-delay="100">
                <!-- Kolom Kiri: 4 Kontak Box Jelas & Besar -->
                <div class="col-lg-6 col-12">
                    <!-- Alamat -->
                    <div class="contact-box">
                        <div class="contact-box-icon c-icon-blue">
                            <i class="bi bi-geo-alt-fill"></i>
                        </div>
                        <div class="contact-box-info">
                            <div class="contact-box-label">Alamat Rumah Sakit</div>
                            <div class="contact-box-val">
                                Jl. A. Yani No.KM. 2, RW.3, Kebun Bunga, Kec. Banjarmasin Tim., Kota Banjarmasin, Kalimantan Selatan 70237
                            </div>
                            <a href="https://maps.google.com/?q=RS+Bhayangkara+Banjarmasin" target="_blank" class="contact-box-link">
                                Buka di Google Maps <i class="bi bi-arrow-up-right"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Telepon -->
                    <div class="contact-box">
                        <div class="contact-box-icon c-icon-green">
                            <i class="bi bi-telephone-fill"></i>
                        </div>
                        <div class="contact-box-info">
                            <div class="contact-box-label">Call Center & IGD (Layanan 24 Jam)</div>
                            <div class="contact-box-val tel-big">
                                0812-3456-7890 / (0511) 3256088
                            </div>
                            <a href="tel:081234567890" class="contact-box-link">
                                Hubungi Sekarang <i class="bi bi-telephone-outbound"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="contact-box">
                        <div class="contact-box-icon c-icon-amber">
                            <i class="bi bi-envelope-open-fill"></i>
                        </div>
                        <div class="contact-box-info">
                            <div class="contact-box-label">Email Resmi Informasi</div>
                            <div class="contact-box-val email-val">
                                rsbhybjm@gmail.com
                            </div>
                            <a href="mailto:rsbhybjm@gmail.com" class="contact-box-link">
                                Kirim Email <i class="bi bi-send"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Website -->
                    <div class="contact-box">
                        <div class="contact-box-icon c-icon-cyan">
                            <i class="bi bi-globe2"></i>
                        </div>
                        <div class="contact-box-info">
                            <div class="contact-box-label">Website Resmi</div>
                            <div class="contact-box-val web-val">
                                rsbhayangkarabanjarmasin.kalsel.polri.go.id
                            </div>
                            <a href="https://rsbhayangkarabanjarmasin.kalsel.polri.go.id" target="_blank" class="contact-box-link">
                                Kunjungi Website <i class="bi bi-box-arrow-up-right"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Kolom Kanan: Peta Google Maps Interaktif -->
                <div class="col-lg-6 col-12">
                    <div class="map-wrapper">
                        <div class="map-header-bar">
                            <div class="map-title">
                                <span class="pulse-dot"></span>
                                Peta Lokasi: RS Bhayangkara Banjarmasin
                            </div>
                            <a href="https://maps.google.com/?q=RS+Bhayangkara+Banjarmasin" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">
                                <i class="bi bi-compass me-1"></i> Rute
                            </a>
                        </div>
                        <iframe 
                            class="map-frame"
                            src="https://maps.google.com/maps?q=RS%20Bhayangkara%20Banjarmasin%20Jl.%20A.%20Yani&t=&z=16&ie=UTF8&iwloc=&output=embed" 
                            allowfullscreen="" 
                            loading="lazy">
                        </iframe>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer>
        © 2025 RS Bhayangkara. Semua hak dilindungi. | Created by <strong>Maria Olpah</strong>
    </footer>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 900,
            once: true
        });
    </script>
</body>

</html>