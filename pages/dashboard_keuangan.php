<?php
session_start();

if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../login.php");
    exit;
}

// Cek level hak akses
if (($_SESSION['level'] ?? '') !== 'keuangan' && ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: dashboard_user.php");
    exit;
}

include __DIR__ . '/../config/koneksi.php';

$nama_user = $_SESSION['nama_pengguna'] ?? 'Staf Keuangan';
$nip_user  = $_SESSION['nip'] ?? '-';
$thn_skrg  = (int)date('Y');

// ================= HITUNG KEUANGAN ASET ================= //
$statSumQ = mysqli_query($koneksi, "SELECT harga, total_stok, tanggal_masuk, umur_ekonomis, kategori_aset FROM aset WHERE harga > 0");

$total_unit = 0;
$total_harga_awal = 0;
$total_nilai_saat_ini = 0;
$total_beban_tahun_ini = 0;
$total_nilai_medis = 0;
$total_nilai_non_medis = 0;

while ($row = mysqli_fetch_assoc($statSumQ)) {
    $stok = (int)($row['total_stok'] ?? 0);
    $total_unit += $stok;
    $harga_total = (float)$row['harga'] * $stok;
    $total_harga_awal += $harga_total;

    if ($row['kategori_aset'] == 'Medis') {
        $total_nilai_medis += $harga_total;
    } else {
        $total_nilai_non_medis += $harga_total;
    }

    $umur = (int)($row['umur_ekonomis'] ?? 0);
    $thn_masuk = (int)date('Y', strtotime($row['tanggal_masuk']));

    if ($umur > 0) {
        $susut_per_tahun = $harga_total / $umur;
        $selisih_tahun = max(0, min($umur, $thn_skrg - $thn_masuk));
        $beban_tahun_ini = ($selisih_tahun < $umur) ? $susut_per_tahun : 0;
        $akumulasi = min($harga_total, ($selisih_tahun + 1) * $susut_per_tahun);
        $nilai_buku = max(0, $harga_total - $akumulasi);

        $total_beban_tahun_ini += $beban_tahun_ini;
        $total_nilai_saat_ini += $nilai_buku;
    } else {
        $total_nilai_saat_ini += $harga_total;
    }
}

// 5 Aset dengan Nilai Investasi Tertinggi
$qTop = mysqli_query($koneksi, "
    SELECT nama_aset, kategori_aset, lokasi, total_stok, harga, (harga * total_stok) as total_investasi, umur_ekonomis
    FROM aset 
    WHERE harga > 0
    ORDER BY total_investasi DESC 
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Keuangan & Akuntansi Aset | SIMARIS RS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8fafc;
        }

        #wrapper {
            display: flex;
            min-height: 100vh;
        }

        #page-content-wrapper {
            flex: 1;
            padding: 0;
        }

        .dashboard-header {
            background: linear-gradient(90deg, #1e3a8a, #0d9488);
            color: #fff;
            padding: 24px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #fff;
        }

        .content {
            padding: 30px;
        }

        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
            border-left: 5px solid #1e3a8a;
            height: 100%;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .card-header-theme {
            background: linear-gradient(90deg, #1e3a8a, #0d9488);
            color: #fff;
            font-weight: 600;
        }

        .progress-bar-medis {
            background: linear-gradient(90deg, #ef4444, #f87171);
        }

        .progress-bar-nonmedis {
            background: linear-gradient(90deg, #3b82f6, #60a5fa);
        }
    </style>
</head>

<body>
    <div id="wrapper">
        <?php include __DIR__ . '/sidebar_keuangan.php'; ?>

        <div id="page-content-wrapper">
            <div class="dashboard-header">
                <div>
                    <h3 class="mb-1 fw-bold"><i class="bi bi-wallet2 me-2"></i>Dashboard Bagian Keuangan</h3>
                    <small class="text-white-50">Monitoring Valuasi Nilai Investasi, Beban Depresiasi & Rekonsiliasi Aset</small>
                </div>
                <div class="text-end">
                    <span class="badge bg-light text-dark px-3 py-2 fs-6">
                        <i class="bi bi-person-badge-fill me-1 text-primary"></i> <?= htmlspecialchars($nama_user) ?>
                    </span>
                    <div class="small text-white-50 mt-1">NIP: <?= htmlspecialchars($nip_user) ?></div>
                </div>
            </div>

            <div class="content">
                <!-- BANNER SAMBUTAN -->
                <div class="alert alert-primary border-0 shadow-sm d-flex justify-content-between align-items-center mb-4 p-3" style="border-radius: 12px; background: linear-gradient(90deg, #dbeafe, #e0f2fe);">
                    <div>
                        <h6 class="fw-bold text-primary mb-1"><i class="bi bi-info-circle-fill me-2"></i>Selamat Datang di Portal Pelaporan Keuangan Aset RS Bhayangkara</h6>
                        <span class="text-dark small">Anda memiliki akses khusus untuk memantau nilai aset, jadwal penyusutan periodik, dan laporan hasil stock opname untuk lampiran laporan keuangan.</span>
                    </div>
                    <div>
                        <a href="laporan_penyusutan_periode.php" class="btn btn-primary btn-sm fw-semibold shadow-sm">
                            <i class="bi bi-file-earmark-bar-graph me-1"></i> Buka Laporan Penyusutan
                        </a>
                    </div>
                </div>

                <!-- 4 KARTU STATISTIK KEUANGAN -->
                <div class="row g-3 mb-4">
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-card border-primary">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <small class="text-muted text-uppercase fw-semibold">Nilai Perolehan (Investasi)</small>
                                <span class="badge bg-primary-subtle text-primary"><i class="bi bi-cash-stack"></i></span>
                            </div>
                            <h4 class="fw-bold text-dark mb-1">Rp <?= number_format($total_harga_awal, 0, ',', '.') ?></h4>
                            <small class="text-muted"><?= $total_unit ?> Unit Aset Terdaftar</small>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-card border-success">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <small class="text-muted text-uppercase fw-semibold">Nilai Buku (Saat Ini)</small>
                                <span class="badge bg-success-subtle text-success"><i class="bi bi-shield-check"></i></span>
                            </div>
                            <h4 class="fw-bold text-success mb-1">Rp <?= number_format($total_nilai_saat_ini, 0, ',', '.') ?></h4>
                            <small class="text-muted">Setelah dikurangi depresiasi</small>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-card border-warning">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <small class="text-muted text-uppercase fw-semibold">Beban Susut Tahun <?= $thn_skrg ?></small>
                                <span class="badge bg-warning-subtle text-warning-emphasis"><i class="bi bi-graph-down-arrow"></i></span>
                            </div>
                            <h4 class="fw-bold text-warning-emphasis mb-1">Rp <?= number_format($total_beban_tahun_ini, 0, ',', '.') ?></h4>
                            <small class="text-muted">Penyusutan periode berjalan</small>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-card border-info">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <small class="text-muted text-uppercase fw-semibold">Status Aset Aktif</small>
                                <span class="badge bg-info-subtle text-info"><i class="bi bi-building-check"></i></span>
                            </div>
                            <h4 class="fw-bold text-info mb-1"><?= $total_unit ?> Unit</h4>
                            <small class="text-muted">Tercatat di SIMARIS RS</small>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <!-- KOMPOSISI NILAI ASET (MEDIS VS NON MEDIS) -->
                    <div class="col-lg-5">
                        <div class="card shadow-sm border-0 h-100" style="border-radius: 12px; overflow: hidden;">
                            <div class="card-header card-header-theme py-3">
                                <i class="bi bi-pie-chart-fill me-2"></i>Komposisi Nilai Aset (Perolehan)
                            </div>
                            <div class="card-body p-4">
                                <?php
                                $pct_medis = $total_harga_awal > 0 ? round(($total_nilai_medis / $total_harga_awal) * 100, 1) : 0;
                                $pct_nonmedis = $total_harga_awal > 0 ? round(($total_nilai_non_medis / $total_harga_awal) * 100, 1) : 0;
                                ?>
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="fw-semibold text-danger"><i class="bi bi-hospital me-1"></i>Aset Medis (Alkes / Kedokteran)</span>
                                        <span class="fw-bold text-danger"><?= $pct_medis ?>%</span>
                                    </div>
                                    <div class="progress" style="height: 12px; border-radius: 6px;">
                                        <div class="progress-bar progress-bar-medis" role="progressbar" style="width: <?= $pct_medis ?>%"></div>
                                    </div>
                                    <small class="text-muted mt-1 d-block">Rp <?= number_format($total_nilai_medis, 0, ',', '.') ?></small>
                                </div>

                                <div class="mb-4">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="fw-semibold text-primary"><i class="bi bi-laptop me-1"></i>Aset Non-Medis (Gedung, IT, Mebel)</span>
                                        <span class="fw-bold text-primary"><?= $pct_nonmedis ?>%</span>
                                    </div>
                                    <div class="progress" style="height: 12px; border-radius: 6px;">
                                        <div class="progress-bar progress-bar-nonmedis" role="progressbar" style="width: <?= $pct_nonmedis ?>%"></div>
                                    </div>
                                    <small class="text-muted mt-1 d-block">Rp <?= number_format($total_nilai_non_medis, 0, ',', '.') ?></small>
                                </div>

                                <hr>
                                <div class="p-3 bg-light rounded text-center">
                                    <small class="text-muted d-block mb-1">Metode Akuntansi yang Digunakan</small>
                                    <span class="badge bg-dark px-3 py-2 fs-6">Garis Lurus (Straight-Line Method)</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 5 ASET DENGAN NILAI PEROLEHAN TERTINGGI -->
                    <div class="col-lg-7">
                        <div class="card shadow-sm border-0 h-100" style="border-radius: 12px; overflow: hidden;">
                            <div class="card-header card-header-theme d-flex justify-content-between align-items-center py-3">
                                <span><i class="bi bi-trophy-fill me-2"></i>5 Aset Bernilai Tertinggi (High-Value Assets)</span>
                                <a href="laporan_nilai.php" class="btn btn-light btn-sm fw-semibold">Lihat Semua</a>
                            </div>
                            <div class="card-body p-0 table-responsive">
                                <table class="table table-hover mb-0 align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Nama Aset</th>
                                            <th>Kategori</th>
                                            <th class="text-center">Stok</th>
                                            <th class="text-end">Harga Satuan</th>
                                            <th class="text-end">Total Nilai</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($top = mysqli_fetch_assoc($qTop)): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold text-dark"><?= htmlspecialchars($top['nama_aset']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($top['lokasi']) ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge <?= $top['kategori_aset'] == 'Medis' ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary' ?>">
                                                        <?= htmlspecialchars($top['kategori_aset']) ?>
                                                    </span>
                                                </td>
                                                <td class="text-center fw-bold"><?= $top['total_stok'] ?></td>
                                                <td class="text-end">Rp <?= number_format($top['harga'], 0, ',', '.') ?></td>
                                                <td class="text-end fw-bold text-success">Rp <?= number_format($top['total_investasi'], 0, ',', '.') ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MENU CEPAT LAPORAN FINANSIAL -->
                <div class="card shadow-sm border-0" style="border-radius: 12px;">
                    <div class="card-body p-4">
                        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-printer me-2"></i>Akses Cepat Laporan Finansial & Akuntansi</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <a href="laporan_penyusutan_periode.php" class="card card-body border text-decoration-none text-dark h-100 p-3 shadow-sm hover-shadow">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="p-3 bg-primary-subtle text-primary rounded-3 fs-3"><i class="bi bi-graph-down-arrow"></i></div>
                                        <div>
                                            <h6 class="fw-bold mb-1">Laporan Penyusutan</h6>
                                            <small class="text-muted">Perhitungan beban depresiasi & nilai buku per periode tahun.</small>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="laporan_stock_opname.php" class="card card-body border text-decoration-none text-dark h-100 p-3 shadow-sm hover-shadow">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="p-3 bg-success-subtle text-success rounded-3 fs-3"><i class="bi bi-clipboard2-check"></i></div>
                                        <div>
                                            <h6 class="fw-bold mb-1">Laporan Stock Opname</h6>
                                            <small class="text-muted">Hasil rekonsiliasi selisih fisik vs sistem di lapangan.</small>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="laporan_nilai.php" class="card card-body border text-decoration-none text-dark h-100 p-3 shadow-sm hover-shadow">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="p-3 bg-warning-subtle text-warning rounded-3 fs-3"><i class="bi bi-cash-stack"></i></div>
                                        <div>
                                            <h6 class="fw-bold mb-1">Data Nilai Aset</h6>
                                            <small class="text-muted">Daftar lengkap harga perolehan seluruh unit aset.</small>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
