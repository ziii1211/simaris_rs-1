<?php
session_start();
if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../login.php");
    exit;
}

include(__DIR__ . '/../config/koneksi.php');

$nama_pengguna = $_SESSION['nama_pengguna'] ?? 'Petugas';
$role = $_SESSION['level'] ?? $_SESSION['role'] ?? 'user';

// Ambil list ruangan untuk dropdown mutasi cepat
$qRuangan = mysqli_query($koneksi, "SELECT DISTINCT lokasi FROM aset WHERE lokasi IS NOT NULL AND lokasi != '' ORDER BY lokasi ASC");
$listRuangan = [];
while ($r = mysqli_fetch_assoc($qRuangan)) {
    $listRuangan[] = $r['lokasi'];
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pemindai QR Code & Barcode Aset | SIMARIS RS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto+Mono:wght@600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f6f9;
            color: #333;
            margin: 0;
        }

        #wrapper {
            display: flex;
            min-height: 100vh;
        }

        #page-content-wrapper {
            flex: 1;
            max-width: 100%;
            overflow-x: hidden;
        }

        .dashboard-header {
            background: linear-gradient(90deg, #1e3a8a, #0d9488);
            color: #fff;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .content {
            padding: 25px;
        }

        .card-scanner {
            background: #ffffff;
            border-radius: 16px;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        /* Viewfinder Camera Box */
        .reader-wrapper {
            position: relative;
            background: #0f172a;
            border-radius: 16px;
            overflow: hidden;
            min-height: 320px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #qr-reader {
            width: 100% !important;
            border: none !important;
            background: transparent !important;
        }

        #qr-reader video {
            border-radius: 12px;
            object-fit: cover;
        }

        /* Laser Scan Line Animation */
        .laser-line {
            position: absolute;
            left: 10%;
            right: 10%;
            height: 3px;
            background: linear-gradient(90deg, transparent, #22c55e, #38bdf8, #22c55e, transparent);
            box-shadow: 0 0 12px #22c55e;
            top: 20%;
            animation: scanLaser 2s ease-in-out infinite alternate;
            z-index: 10;
            pointer-events: none;
        }

        @keyframes scanLaser {
            0% { top: 15%; opacity: 0.8; }
            100% { top: 85%; opacity: 1; }
        }

        .badge-code {
            font-family: 'Roboto Mono', monospace;
            font-size: 1.1rem;
            background: #0f172a;
            color: #38bdf8;
            padding: 4px 12px;
            border-radius: 6px;
            display: inline-block;
        }

        .result-card {
            display: none;
            border-left: 6px solid #0d9488;
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>

<body>
    <div id="wrapper">
        <?php 
        $role_user = $_SESSION['level'] ?? $_SESSION['role'] ?? '';
        if ($role_user === 'keuangan') {
            include(__DIR__ . '/sidebar_keuangan.php');
        } elseif ($role_user === 'teknisi') {
            include(__DIR__ . '/sidebar_teknisi.php');
        } elseif ($role_user === 'user') {
            include(__DIR__ . '/sidebar_user.php');
        } else {
            include(__DIR__ . '/../sidebar.php');
        }
        ?>

        <div id="page-content-wrapper">
            <div class="dashboard-header">
                <div>
                    <h4 class="fw-bold m-0"><i class="bi bi-qr-code-scan"></i> PEMINDAI QR CODE & BARCODE ASET</h4>
                    <div class="small text-white-50">Pelacakan Cepat, Validasi Fisik, dan Pemindahan Ruangan Otomatis</div>
                </div>
                <div class="small fw-medium">
                    <i class="bi bi-person-circle-fill"></i> <?= htmlspecialchars($nama_pengguna); ?>
                    <span class="badge bg-light text-dark ms-1" style="text-transform: uppercase;"><?= htmlspecialchars($role); ?></span>
                </div>
            </div>

            <div class="content">
                <div class="row g-4">
                    <!-- Scanner Column -->
                    <div class="col-lg-6">
                        <div class="card card-scanner h-100">
                            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-dark"><i class="bi bi-camera-fill text-primary me-1"></i> Kamera Pemindai (Live Scanner)</span>
                                <span id="camera-status" class="badge bg-secondary">Standby</span>
                            </div>

                            <div class="card-body p-3">
                                <!-- Viewfinder wrapper -->
                                <div class="reader-wrapper">
                                    <div id="laser-line" class="laser-line" style="display: none;"></div>
                                    <div id="qr-reader"></div>
                                    <div id="camera-placeholder" class="text-center text-white-50 p-4">
                                        <i class="bi bi-qr-code text-light opacity-50" style="font-size: 4rem;"></i>
                                        <div class="mt-2 fw-semibold text-light">Kamera Belum Aktif</div>
                                        <div class="small text-secondary">Klik tombol 'Aktifkan Kamera' di bawah untuk mulai memindai label fisik aset.</div>
                                    </div>
                                </div>

                                <!-- Camera Controls -->
                                <div class="d-flex gap-2 mt-3 flex-wrap">
                                    <button id="btn-start" class="btn btn-primary btn-sm flex-fill fw-bold" onclick="startScanner()">
                                        <i class="bi bi-camera-video-fill me-1"></i> Aktifkan Kamera
                                    </button>
                                    <button id="btn-stop" class="btn btn-outline-danger btn-sm" onclick="stopScanner()" style="display: none;">
                                        <i class="bi bi-stop-circle me-1"></i> Nonaktifkan
                                    </button>
                                    <button id="btn-switch" class="btn btn-outline-secondary btn-sm" onclick="switchCamera()" style="display: none;">
                                        <i class="bi bi-arrow-repeat me-1"></i> Ganti Kamera
                                    </button>
                                    <label class="btn btn-outline-primary btn-sm fw-semibold" title="Pindai QR dari file foto atau galeri">
                                        <i class="bi bi-file-earmark-image me-1"></i> Unggah Gambar QR
                                        <input type="file" id="qr-input-file" accept="image/*" style="display: none;" onchange="scanImageFile(this)">
                                    </label>
                                </div>

                                <!-- Manual Input Form -->
                                <div class="mt-4 pt-3 border-top">
                                    <label class="form-label small fw-bold text-secondary">
                                        <i class="bi bi-upc-scan me-1"></i> Barcode Gun Scanner USB / Input Manual Kode:
                                    </label>
                                    <form id="form-manual" onsubmit="event.preventDefault(); prosesLookupManual();" class="input-group">
                                        <input type="text" id="manual-code" class="form-control" placeholder="Contoh: RSB-MED-018 atau scan barcode..." autofocus autocomplete="off">
                                        <button class="btn btn-dark" type="submit">
                                            <i class="bi bi-search me-1"></i> Cari Aset
                                        </button>
                                    </form>
                                    <div class="form-text small text-muted">
                                        Mendukung barcode scanner USB (plug & play) atau pemindaian kamera smartphone langsung.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Result Column -->
                    <div class="col-lg-6">
                        <!-- Initial Prompt -->
                        <div id="empty-prompt" class="card card-scanner p-5 text-center text-muted">
                            <i class="bi bi-upc text-secondary opacity-50" style="font-size: 5rem;"></i>
                            <h5 class="fw-bold text-dark mt-3">Menunggu Pemindaian Label</h5>
                            <p class="small text-secondary mb-0">
                                Arahkan kamera ke stiker QR Code pada barang/alat kesehatan atau masukkan kode aset di sebelah kiri untuk melihat rincian dan melakukan pemindahan ruangan (mutasi).
                            </p>
                        </div>

                        <!-- Result Card -->
                        <div id="result-card" class="card card-scanner result-card p-4">
                            <div class="d-flex justify-content-between align-items-start border-bottom pb-3">
                                <div>
                                    <span id="res-kode" class="badge-code">RSB-MED-000</span>
                                    <span id="res-kategori" class="badge bg-danger ms-2">Medis</span>
                                    <h4 id="res-nama" class="fw-bold text-dark mt-2 mb-1">-</h4>
                                    <div id="res-tipe" class="text-secondary small">-</div>
                                </div>
                                <div>
                                    <button class="btn btn-outline-secondary btn-sm" onclick="resetScan()" title="Scan Aset Lain">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Posisi Ruangan Terkini -->
                            <div class="p-3 my-3 rounded-3 bg-light border">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="small text-secondary fw-semibold">LOKASI RUANGAN SAAT INI</div>
                                        <h5 id="res-lokasi" class="fw-bold text-danger mb-0">
                                            <i class="bi bi-geo-alt-fill"></i> -
                                        </h5>
                                    </div>
                                    <div class="text-end">
                                        <div class="small text-secondary fw-semibold">TOTAL STOK</div>
                                        <span id="res-total-stok" class="badge bg-primary fs-6">1 Unit</span>
                                    </div>
                                </div>
                                <div class="mt-2 pt-2 border-top d-flex gap-2">
                                    <span id="res-stok-tersedia" class="badge bg-success">Tersedia: 0</span>
                                    <span id="res-stok-rusak" class="badge bg-danger">Rusak: 0</span>
                                    <span id="res-stok-perawatan" class="badge bg-warning text-dark">Perawatan: 0</span>
                                </div>
                            </div>

                            <!-- Quick Relocation Form -->
                            <div class="card p-3 border-0 bg-primary bg-opacity-10 rounded-3 mb-3">
                                <h6 class="fw-bold text-primary mb-2">
                                    <i class="bi bi-arrow-left-right me-1"></i> Quick Action: Pindahkan Ruangan (Mutasi)
                                </h6>
                                <form id="form-quick-move" onsubmit="event.preventDefault(); prosesQuickMove();">
                                    <input type="hidden" id="move-id-aset" value="">
                                    <div class="mb-2">
                                        <select id="move-lokasi-baru" class="form-select form-select-sm" required>
                                            <option value="">-- Pilih Ruangan Tujuan Baru --</option>
                                            <?php foreach ($listRuangan as $ruang): ?>
                                                <option value="<?= htmlspecialchars($ruang) ?>"><?= htmlspecialchars($ruang) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <input type="text" id="move-keterangan" class="form-control form-control-sm" placeholder="Alasan perpindahan (opsional)">
                                    </div>
                                    <button type="submit" id="btn-submit-move" class="btn btn-primary btn-sm w-100 fw-bold">
                                        <i class="bi bi-check2-circle me-1"></i> Konfirmasi Pindah Ruangan
                                    </button>
                                </form>
                            </div>

                            <!-- Detail & History Links -->
                            <div class="d-flex justify-content-between align-items-center">
                                <a id="link-detail" href="#" target="_blank" class="btn btn-outline-dark btn-sm">
                                    <i class="bi bi-box-arrow-up-right me-1"></i> Halaman Detail Lengkap
                                </a>
                                <button class="btn btn-success btn-sm fw-bold" onclick="resetScan()">
                                    <i class="bi bi-qr-code-scan me-1"></i> Lanjut Scan Aset Lain
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Feedback -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="liveToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="toastMessage">
                    Aset berhasil dipindahkan!
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <!-- Modal Panduan Secure Context Kamera -->
    <div class="modal fade" id="modalSecureContext" tabindex="-1" aria-labelledby="modalSecureContextLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-warning text-dark py-3 px-4">
                    <h5 class="modal-title fw-bold" id="modalSecureContextLabel">
                        <i class="bi bi-shield-lock-fill me-2"></i> Akses Kamera Membutuhkan Izin Browser
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-danger d-flex align-items-center mb-3">
                        <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                        <div>
                            <strong>Penyebab Kamera Tidak Nyala:</strong><br>
                            Browser (Microsoft Edge / Google Chrome) secara ketat memblokir akses webcam pada domain HTTP (<code>simaris_rs.test</code>) demi keamanan, kecuali diizinkan di setelan browser atau diakses lewat <code>localhost</code>.
                        </div>
                    </div>

                    <p class="text-secondary small fw-medium mb-3">
                        Pilih salah satu solusi di bawah ini untuk menyalakan kamera atau memindai aset:
                    </p>

                    <div class="accordion" id="accordionHelp">
                        <!-- Opsi 1: Rekomendasi Edge / Chrome Flag -->
                        <div class="accordion-item border rounded-3 mb-3 overflow-hidden shadow-sm">
                            <h2 class="accordion-header" id="headingOne">
                                <button class="accordion-button fw-bold text-primary bg-primary bg-opacity-10" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                    <i class="bi bi-star-fill text-warning me-2"></i> Cara 1: Izinkan di Browser (Sangat Direkomendasikan - Cukup 1x Seting)
                                </button>
                            </h2>
                            <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#accordionHelp">
                                <div class="accordion-body bg-white">
                                    <p class="small text-muted mb-2">Ikuti 4 langkah mudah berikut di browser Anda:</p>
                                    <ol class="small text-secondary ps-3 mb-3" style="line-height: 1.8;">
                                        <li>Buka <strong>tab baru</strong> di browser Edge/Chrome Anda.</li>
                                        <li>Ketik atau salin URL flags berikut ke address bar lalu tekan <strong>Enter</strong>:
                                            <div class="input-group input-group-sm my-2">
                                                <input type="text" class="form-control font-monospace bg-light" id="edge-flag-url" value="edge://flags/#unsafely-treat-insecure-origin-as-secure" readonly>
                                                <button class="btn btn-primary" type="button" onclick="copyText('edge-flag-url')">
                                                    <i class="bi bi-clipboard me-1"></i> Salin Link Edge
                                                </button>
                                            </div>
                                            <div class="text-muted" style="font-size: 0.8rem;">
                                                <em>*Bagi pengguna Google Chrome, gunakan: <code>chrome://flags/#unsafely-treat-insecure-origin-as-secure</code></em>
                                            </div>
                                        </li>
                                        <li>Cari opsi <strong>"Insecure origins treated as secure"</strong>, ubah statusnya dari <span class="badge bg-secondary">Disabled</span> menjadi <span class="badge bg-success">Enabled</span>.</li>
                                        <li>Pada kotak isian teks di bawahnya, masukkan URL SIMARIS:
                                            <div class="input-group input-group-sm my-2">
                                                <input type="text" class="form-control font-monospace bg-light" id="origin-url" value="http://simaris_rs.test:8080" readonly>
                                                <button class="btn btn-outline-secondary" type="button" onclick="copyText('origin-url')">
                                                    <i class="bi bi-clipboard me-1"></i> Salin URL
                                                </button>
                                            </div>
                                        </li>
                                        <li>Klik tombol biru <strong>Relaunch / Muat Ulang</strong> di pojok kanan bawah browser.</li>
                                        <li>Buka kembali halaman ini, klik <strong>Aktifkan Kamera</strong>, lalu pilih <strong>Allow / Izinkan</strong> saat diminta!</li>
                                    </ol>
                                </div>
                            </div>
                        </div>

                        <!-- Opsi 2: Buka via Localhost -->
                        <div class="accordion-item border rounded-3 mb-3 overflow-hidden shadow-sm">
                            <h2 class="accordion-header" id="headingTwo">
                                <button class="accordion-button collapsed fw-bold text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                    <i class="bi bi-laptop me-2 text-info"></i> Cara 2: Buka via Localhost (Tanpa Ubah Setelan Browser)
                                </button>
                            </h2>
                            <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#accordionHelp">
                                <div class="accordion-body bg-white">
                                    <p class="small text-secondary mb-3">
                                        Browser secara otomatis menganggap alamat <code>localhost</code> sebagai Secure Context sehingga kamera langsung diizinkan:
                                    </p>
                                    <a id="link-localhost" href="http://localhost:8080/simaris_rs/pages/scan_aset.php" class="btn btn-outline-primary btn-sm fw-bold">
                                        <i class="bi bi-box-arrow-up-right me-1"></i> Buka via http://localhost:8080/simaris_rs/...
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Opsi 3: Gunakan File Gambar / Barcode Scanner -->
                        <div class="accordion-item border rounded-3 overflow-hidden shadow-sm">
                            <h2 class="accordion-header" id="headingThree">
                                <button class="accordion-button collapsed fw-bold text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                    <i class="bi bi-image me-2 text-success"></i> Cara 3: Pindai File Gambar QR / Alat Scanner USB
                                </button>
                            </h2>
                            <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#accordionHelp">
                                <div class="accordion-body bg-white">
                                    <p class="small text-secondary mb-2">
                                        Anda tetap dapat memindai label aset tanpa kamera live:
                                    </p>
                                    <ul class="small text-secondary ps-3 mb-0">
                                        <li>Gunakan tombol <strong>"Unggah Gambar QR"</strong> untuk memilih file gambar/foto stiker QR.</li>
                                        <li>Gunakan barcode scanner gun USB (plug & play) pada kolom input manual.</li>
                                        <li>Ketik langsung kode aset (contoh: <code>RSB-MED-001</code>) lalu tekan Cari.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-4">
                    <button type="button" class="btn btn-secondary btn-sm px-4 fw-medium" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

    <script>
        let html5QrCode = null;
        let isScanning = false;
        let currentCameraId = null;
        let cameras = [];
        let cameraIndex = 0;

        // Lepas kamera secara otomatis saat tab ditutup atau di-refresh
        window.addEventListener('beforeunload', () => {
            if (html5QrCode && isScanning) {
                try { html5QrCode.stop(); } catch (e) {}
            }
        });

        function showSecureContextModal() {
            const modalEl = document.getElementById('modalSecureContext');
            if (modalEl) {
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
            }
        }

        function copyText(inputId) {
            const input = document.getElementById(inputId);
            if (!input) return;
            input.select();
            input.setSelectionRange(0, 99999);
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(input.value).then(() => {
                    showToast('Berhasil disalin: ' + input.value, true);
                }).catch(() => {
                    document.execCommand('copy');
                    showToast('Berhasil disalin: ' + input.value, true);
                });
            } else {
                document.execCommand('copy');
                showToast('Berhasil disalin: ' + input.value, true);
            }
        }

        // Audio Beep generator via Web Audio API
        function playBeep() {
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.type = "sine";
                osc.frequency.setValueAtTime(880, audioCtx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(1760, audioCtx.currentTime + 0.15);
                gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.15);
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.start();
                osc.stop(audioCtx.currentTime + 0.15);
            } catch (e) {
                console.log("Audio API not supported or blocked");
            }

            if (navigator.vibrate) {
                navigator.vibrate(150);
            }
        }

        function showToast(msg, isSuccess = true) {
            const toastEl = document.getElementById('liveToast');
            toastEl.className = 'toast align-items-center text-bg-' + (isSuccess ? 'success' : 'danger') + ' border-0';
            document.getElementById('toastMessage').innerText = msg;
            const toast = new bootstrap.Toast(toastEl);
            toast.show();
        }

        // Inisialisasi Scanner Kamera
        async function startScanner() {
            const isLocal = ['localhost', '127.0.0.1'].includes(window.location.hostname);
            const isSecure = window.isSecureContext || isLocal;

            // Jika browser tidak dalam Secure Context, langsung tampilkan modal bantuan
            if (!isSecure) {
                showSecureContextModal();
                return;
            }

            try {
                // Bersihkan scanner sebelumnya jika masih menggantung
                if (html5QrCode) {
                    try {
                        if (isScanning) {
                            await html5QrCode.stop();
                        }
                        html5QrCode.clear();
                    } catch (cleanErr) {
                        console.warn("Cleanup error:", cleanErr);
                    }
                }
                html5QrCode = new Html5Qrcode("qr-reader");

                document.getElementById('camera-placeholder').style.display = 'none';
                document.getElementById('laser-line').style.display = 'block';
                document.getElementById('camera-status').className = 'badge bg-warning text-dark';
                document.getElementById('camera-status').innerText = 'Memuat Kamera...';

                // Dapatkan daftar kamera jika didukung
                let cameraIdOrConfig = { facingMode: "environment" };
                try {
                    cameras = await Html5Qrcode.getCameras();
                    if (cameras && cameras.length > 0) {
                        let selectedCam = cameras[0].id;
                        for (let i = 0; i < cameras.length; i++) {
                            const lbl = (cameras[i].label || '').toLowerCase();
                            if (lbl.includes('back') || lbl.includes('rear') || lbl.includes('environment')) {
                                selectedCam = cameras[i].id;
                                cameraIndex = i;
                                break;
                            }
                        }
                        currentCameraId = selectedCam;
                        cameraIdOrConfig = currentCameraId;
                    }
                } catch (camErr) {
                    console.warn("getCameras fallback facingMode:", camErr);
                }

                await html5QrCode.start(
                    cameraIdOrConfig,
                    {
                        fps: 10,
                        qrbox: (viewfinderWidth, viewfinderHeight) => {
                            const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
                            const edge = Math.floor(minEdge * 0.75);
                            return { width: edge, height: edge };
                        },
                        aspectRatio: 1.0
                    },
                    (decodedText, decodedResult) => {
                        onScanSuccess(decodedText);
                    },
                    (errorMessage) => {
                        // scanning frame
                    }
                );

                isScanning = true;
                document.getElementById('camera-status').className = 'badge bg-success';
                document.getElementById('camera-status').innerText = 'Kamera Aktif';
                document.getElementById('btn-start').style.display = 'none';
                document.getElementById('btn-stop').style.display = 'inline-block';
                if (cameras && cameras.length > 1) {
                    document.getElementById('btn-switch').style.display = 'inline-block';
                }
            } catch (err) {
                console.error("Gagal memulai kamera:", err);
                document.getElementById('camera-status').className = 'badge bg-danger';
                document.getElementById('camera-status').innerText = 'Kamera Gagal';
                document.getElementById('laser-line').style.display = 'none';
                document.getElementById('camera-placeholder').style.display = 'block';

                const errStr = String(err).toLowerCase();
                if (errStr.includes("notreadableerror") || errStr.includes("device in use") || errStr.includes("in use")) {
                    alert("Kamera sedang digunakan oleh tab lain atau aplikasi lain di komputer Anda (seperti tab browser yang masih terbuka, Zoom, Meet, atau aplikasi Camera Windows).\n\nSilakan tutup tab atau aplikasi tersebut, tunggu sekitar 3 detik, lalu klik 'Aktifkan Kamera' kembali.");
                } else if (errStr.includes("secure context") || errStr.includes("supported in secure context") || !window.isSecureContext) {
                    showSecureContextModal();
                } else if (errStr.includes("notallowederror") || errStr.includes("permission denied")) {
                    alert("Izin kamera ditolak oleh browser. Silakan klik ikon kamera atau gembok di samping address bar browser Anda dan pilih 'Allow / Izinkan'.");
                } else if (errStr.includes("notfounderror") || errStr.includes("devicesnotfounderror")) {
                    alert("Tidak ada perangkat kamera/webcam yang ditemukan pada komputer ini.");
                } else {
                    alert("Tidak dapat mengakses kamera: " + err);
                }
            }
        }

        async function stopScanner() {
            if (html5QrCode && isScanning) {
                await html5QrCode.stop();
                html5QrCode.clear();
                isScanning = false;
                document.getElementById('laser-line').style.display = 'none';
                document.getElementById('camera-placeholder').style.display = 'block';
                document.getElementById('camera-status').className = 'badge bg-secondary';
                document.getElementById('camera-status').innerText = 'Standby';
                document.getElementById('btn-start').style.display = 'inline-block';
                document.getElementById('btn-stop').style.display = 'none';
                document.getElementById('btn-switch').style.display = 'none';
            }
        }

        async function switchCamera() {
            if (cameras.length > 1 && isScanning) {
                await stopScanner();
                cameraIndex = (cameraIndex + 1) % cameras.length;
                currentCameraId = cameras[cameraIndex].id;
                await startScanner();
            }
        }

        // Pindai QR Code dari File / Gambar yang Diunggah
        async function scanImageFile(input) {
            if (!input.files || input.files.length === 0) return;
            const file = input.files[0];
            try {
                if (isScanning) {
                    await stopScanner();
                }
                if (!html5QrCode) {
                    html5QrCode = new Html5Qrcode("qr-reader");
                }
                document.getElementById('camera-placeholder').style.display = 'none';
                document.getElementById('laser-line').style.display = 'none';
                document.getElementById('camera-status').className = 'badge bg-info';
                document.getElementById('camera-status').innerText = 'Memindai Gambar...';

                const decodedText = await html5QrCode.scanFile(file, true);
                document.getElementById('camera-status').className = 'badge bg-success';
                document.getElementById('camera-status').innerText = 'Pindai Berhasil';
                onScanSuccess(decodedText);
            } catch (err) {
                console.error("Gagal memindai gambar:", err);
                document.getElementById('camera-status').className = 'badge bg-secondary';
                document.getElementById('camera-status').innerText = 'Standby';
                alert("QR Code atau Barcode tidak terdeteksi pada gambar ini. Pastikan gambar stiker jelas dan tidak buram.");
            } finally {
                input.value = '';
            }
        }

        // Saat QR Berhasil Terdeteksi
        function onScanSuccess(decodedText) {
            playBeep();
            if (html5QrCode && isScanning) {
                html5QrCode.pause(true);
            }
            fetchAssetData(decodedText);
        }

        function prosesLookupManual() {
            const val = document.getElementById('manual-code').value.trim();
            if (!val) {
                alert('Silakan ketik kode atau scan barcode terlebih dahulu.');
                return;
            }
            fetchAssetData(val);
        }

        // Ambil Data Aset via AJAX
        function fetchAssetData(codeOrUrl) {
            const encoded = encodeURIComponent(codeOrUrl);
            fetch('ajax_get_aset.php?action=lookup&kode=' + encoded)
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        displayAssetResult(res.data);
                    } else {
                        alert(res.message);
                        if (html5QrCode && isScanning) {
                            setTimeout(() => { html5QrCode.resume(); }, 1500);
                        }
                    }
                })
                .catch(err => {
                    alert('Terjadi kesalahan jaringan saat mengambil data aset.');
                    if (html5QrCode && isScanning) {
                        html5QrCode.resume();
                    }
                });
        }

        function displayAssetResult(data) {
            document.getElementById('empty-prompt').style.display = 'none';
            document.getElementById('result-card').style.display = 'block';

            document.getElementById('res-kode').innerText = data.kode_aset;
            document.getElementById('res-kategori').innerText = data.kategori_aset;
            document.getElementById('res-kategori').className = 'badge ' + (data.kategori_aset === 'Medis' ? 'bg-danger' : 'bg-primary') + ' ms-2';
            document.getElementById('res-nama').innerText = data.nama_aset;
            document.getElementById('res-tipe').innerText = 'Jenis: ' + data.jenis + ' | Tipe: ' + data.tipe_aset + ' | Harga: ' + data.harga_format;
            document.getElementById('res-lokasi').innerHTML = '<i class="bi bi-geo-alt-fill"></i> ' + data.lokasi;
            document.getElementById('res-total-stok').innerText = data.total_stok + ' Unit';
            document.getElementById('res-stok-tersedia').innerText = 'Tersedia: ' + data.stok_tersedia;
            document.getElementById('res-stok-rusak').innerText = 'Rusak: ' + data.stok_rusak;
            document.getElementById('res-stok-perawatan').innerText = 'Perawatan: ' + data.stok_perawatan;

            document.getElementById('move-id-aset').value = data.id_aset;
            document.getElementById('move-lokasi-baru').value = '';
            document.getElementById('move-keterangan').value = '';
            document.getElementById('link-detail').href = 'detail_aset_qr.php?kode=' + encodeURIComponent(data.kode_aset);
        }

        function resetScan() {
            document.getElementById('result-card').style.display = 'none';
            document.getElementById('empty-prompt').style.display = 'block';
            document.getElementById('manual-code').value = '';
            document.getElementById('manual-code').focus();

            if (html5QrCode && isScanning) {
                html5QrCode.resume();
            }
        }

        // Proses Mutasi Cepat dari Scanner
        function prosesQuickMove() {
            const idAset = document.getElementById('move-id-aset').value;
            const lokasiBaru = document.getElementById('move-lokasi-baru').value;
            const ket = document.getElementById('move-keterangan').value;

            if (!lokasiBaru) {
                alert('Pilih ruangan baru terlebih dahulu.');
                return;
            }

            const btn = document.getElementById('btn-submit-move');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...';

            const formData = new FormData();
            formData.append('id_aset', idAset);
            formData.append('lokasi_baru', lokasiBaru);
            formData.append('keterangan', ket ? ket : 'Mutasi via Scanner QR Barcode');

            fetch('ajax_get_aset.php?action=pindah_lokasi', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Konfirmasi Pindah Ruangan';

                if (res.success) {
                    showToast(res.message, true);
                    document.getElementById('res-lokasi').innerHTML = '<i class="bi bi-geo-alt-fill"></i> ' + res.lokasi_baru;
                    document.getElementById('move-lokasi-baru').value = '';
                    document.getElementById('move-keterangan').value = '';
                } else {
                    showToast(res.message, false);
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Konfirmasi Pindah Ruangan';
                showToast('Gagal memproses mutasi: Masalah jaringan.', false);
            });
        }
    </script>
</body>

</html>
