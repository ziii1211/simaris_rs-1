<?php
session_start();
if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../login.php");
    exit;
}

include(__DIR__ . '/../config/koneksi.php');

$nama_pengguna = $_SESSION['nama_pengguna'] ?? 'Petugas';
$role = $_SESSION['level'] ?? $_SESSION['role'] ?? 'user';

$tab_aktif = isset($_GET['tab']) ? $_GET['tab'] : 'antrean';
$filter_prioritas = isset($_GET['prioritas']) ? mysqli_real_escape_string($koneksi, $_GET['prioritas']) : '';
$filter_kategori = isset($_GET['kategori']) ? mysqli_real_escape_string($koneksi, $_GET['kategori']) : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($koneksi, trim($_GET['search'])) : '';

// 1. STATISTIK KRITIKALITAS ASET & INFRASTRUKTUR
$qStatAset = mysqli_query($koneksi, "SELECT 
    COUNT(*) as total_aset,
    SUM(tingkat_prioritas = 'Kritis') as total_kritis,
    SUM(tingkat_prioritas = 'Penting') as total_penting,
    SUM(tingkat_prioritas = 'Rutin') as total_rutin,
    SUM(CASE WHEN tingkat_prioritas = 'Kritis' THEN stok_tersedia ELSE 0 END) as kritis_tersedia,
    SUM(CASE WHEN tingkat_prioritas = 'Kritis' THEN total_stok ELSE 0 END) as kritis_total_unit,
    SUM(CASE WHEN tingkat_prioritas = 'Kritis' THEN (harga * total_stok) ELSE 0 END) as nilai_kritis
FROM aset");
$statAset = mysqli_fetch_assoc($qStatAset);

$totalKritisUnit = (int)$statAset['kritis_total_unit'];
$kritisTersedia = (int)$statAset['kritis_tersedia'];
$readinessRate = $totalKritisUnit > 0 ? round(($kritisTersedia / $totalKritisUnit) * 100, 1) : 100;

$qStatInfra = mysqli_query($koneksi, "SELECT 
    COUNT(*) as total_infra,
    SUM(tingkat_prioritas = 'Kritis') as infra_kritis,
    SUM(tingkat_prioritas = 'Penting') as infra_penting,
    SUM(tingkat_prioritas = 'Rutin') as infra_rutin
FROM infrastruktur");
$statInfra = mysqli_fetch_assoc($qStatInfra);

// 2. QUERY ANTREAN PEMELIHARAAN PRIORITAS (Perawatan digabung Aset dengan Prioritas)
$whereP = [];
if (!empty($filter_prioritas)) {
    $whereP[] = "a.tingkat_prioritas = '$filter_prioritas'";
}
if (!empty($filter_kategori)) {
    $whereP[] = "a.kategori_aset = '$filter_kategori'";
}
if (!empty($search)) {
    $whereP[] = "(p.nama_aset LIKE '%$search%' OR a.kode_aset LIKE '%$search%' OR a.lokasi LIKE '%$search%')";
}
$wherePSql = count($whereP) > 0 ? "WHERE " . implode(" AND ", $whereP) : "";

$qQueue = mysqli_query($koneksi, "SELECT 
    p.id, p.nama_aset, p.teknisi, p.petugas_kalibrasi, p.tanggal, p.tanggal_selesai,
    p.tanggal_kalibrasi_berikutnya, p.status, p.biaya_pemeliharaan,
    COALESCE(a.kode_aset, 'RSB-000') as kode_aset,
    COALESCE(a.tingkat_prioritas, 'Penting') as tingkat_prioritas,
    COALESCE(a.lokasi, 'Ruang Rawat') as lokasi,
    COALESCE(a.kategori_aset, 'Medis') as kategori_aset
FROM perawatan p
LEFT JOIN aset a ON TRIM(p.nama_aset) COLLATE utf8mb4_general_ci = TRIM(a.nama_aset) COLLATE utf8mb4_general_ci
$wherePSql
ORDER BY FIELD(COALESCE(a.tingkat_prioritas, 'Penting'), 'Kritis', 'Penting', 'Rutin'), p.tanggal DESC");

$queueList = [];
$countKritisQueue = 0;
while ($row = mysqli_fetch_assoc($qQueue)) {
    if ($row['tingkat_prioritas'] === 'Kritis' && $row['status'] !== 'Selesai') {
        $countKritisQueue++;
    }
    $queueList[] = $row;
}

// 3. QUERY KATALOG ASET KRITIS
$whereAset = [];
if (!empty($filter_prioritas)) {
    $whereAset[] = "tingkat_prioritas = '$filter_prioritas'";
} else {
    // Default tab katalog: tampilkan Kritis dan Penting terlebih dahulu
}
if (!empty($filter_kategori)) {
    $whereAset[] = "kategori_aset = '$filter_kategori'";
}
if (!empty($search)) {
    $whereAset[] = "(nama_aset LIKE '%$search%' OR kode_aset LIKE '%$search%' OR lokasi LIKE '%$search%')";
}
$whereAsetSql = count($whereAset) > 0 ? "WHERE " . implode(" AND ", $whereAset) : "";

$qKatalog = mysqli_query($koneksi, "SELECT * FROM aset $whereAsetSql ORDER BY FIELD(tingkat_prioritas, 'Kritis', 'Penting', 'Rutin'), nama_aset ASC");
$katalogList = [];
while ($ka = mysqli_fetch_assoc($qKatalog)) {
    $katalogList[] = $ka;
}

// 4. QUERY INFRASTRUKTUR KRITIS
$whereInfra = [];
if (!empty($filter_prioritas)) {
    $whereInfra[] = "tingkat_prioritas = '$filter_prioritas'";
}
if (!empty($search)) {
    $whereInfra[] = "(nama_infrastruktur LIKE '%$search%' OR kode_infrastruktur LIKE '%$search%' OR nama_gedung LIKE '%$search%')";
}
$whereInfraSql = count($whereInfra) > 0 ? "WHERE " . implode(" AND ", $whereInfra) : "";

$qInfraList = mysqli_query($koneksi, "SELECT * FROM infrastruktur $whereInfraSql ORDER BY FIELD(tingkat_prioritas, 'Kritis', 'Penting', 'Rutin'), nama_infrastruktur ASC");
$infraList = [];
while ($in = mysqli_fetch_assoc($qInfraList)) {
    $infraList[] = $in;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Klasifikasi Prioritas Aset & Pemeliharaan Kritis | SIMARIS RS</title>
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
            background: linear-gradient(90deg, #991b1b, #0d9488);
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

        .card-stat {
            border: none;
            border-radius: 14px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            background: #fff;
            transition: all 0.2s ease-in-out;
        }

        .card-stat:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.08);
        }

        .badge-kritis {
            background-color: #ef4444 !important;
            color: #fff !important;
            font-weight: 700;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.78rem;
            letter-spacing: 0.3px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-penting {
            background-color: #f59e0b !important;
            color: #000 !important;
            font-weight: 600;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.78rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-rutin {
            background-color: #64748b !important;
            color: #fff !important;
            font-weight: 500;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.78rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .table-custom {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        }

        .table-custom thead th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            font-size: 0.8rem;
            text-transform: uppercase;
            padding: 12px 14px;
            border-bottom: 2px solid #e2e8f0;
        }

        .table-custom tbody td {
            padding: 12px 14px;
            font-size: 0.875rem;
            vertical-align: middle;
        }

        .badge-code {
            font-family: 'Roboto Mono', monospace;
            font-size: 0.85rem;
            background: #0f172a;
            color: #38bdf8;
            padding: 3px 8px;
            border-radius: 4px;
        }

        .nav-tabs .nav-link {
            font-weight: 600;
            color: #64748b;
            border: none;
            border-bottom: 3px solid transparent;
            padding: 12px 20px;
        }

        .nav-tabs .nav-link.active {
            color: #b91c1c;
            border-bottom: 3px solid #b91c1c;
            background: transparent;
        }
    </style>
</head>

<body>
    <div id="wrapper">
        <?php
        if ($role === 'keuangan') {
            include(__DIR__ . '/sidebar_keuangan.php');
        } else {
            include(__DIR__ . '/../sidebar.php');
        }
        ?>

        <div id="page-content-wrapper">
            <!-- Header -->
            <div class="dashboard-header">
                <div>
                    <h4 class="fw-bold m-0"><i class="bi bi-shield-fill-exclamation"></i> KLASIFIKASI PRIORITAS ASET & PEMELIHARAAN KRITIS</h4>
                    <div class="small text-white-50">Penguatan Tata Kelola Alkes Life Support & Infrastruktur Vital Pelayanan Pasien RS Bhayangkara</div>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <a href="cetak_prioritas_pemeliharaan.php?prioritas=<?= urlencode($filter_prioritas) ?>" target="_blank" class="btn btn-light btn-sm fw-bold px-3 rounded-pill">
                        <i class="bi bi-file-earmark-pdf-fill text-danger me-1"></i> Cetak Laporan PDF
                    </a>
                </div>
            </div>

            <div class="content">
                <!-- KPI Stat Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card card-stat p-3 border-start border-danger border-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-danger small fw-bold text-uppercase">ALKES & INFRASTRUKTUR KRITIS</div>
                                    <h3 class="fw-bold text-danger mb-0"><?= $statAset['total_kritis'] ?> Alkes | <?= $statInfra['infra_kritis'] ?> Fasilitas</h3>
                                    <div class="small text-muted"><?= $totalKritisUnit ?> Total unit peralatan darurat/ICU/OK</div>
                                </div>
                                <div class="bg-danger bg-opacity-10 text-danger p-3 rounded-circle fs-4">
                                    <i class="bi bi-heart-pulse-fill"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card card-stat p-3 border-start border-success border-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-success small fw-bold text-uppercase">KESIAPAN ALKES KRITIS (READINESS)</div>
                                    <h3 class="fw-bold text-success mb-0"><?= $readinessRate ?>%</h3>
                                    <div class="small text-muted"><?= $kritisTersedia ?> dari <?= $totalKritisUnit ?> unit siap pakai operasional</div>
                                </div>
                                <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle fs-4">
                                    <i class="bi bi-shield-check"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card card-stat p-3 border-start border-warning border-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-warning small fw-bold text-uppercase">ASET PRIORITAS SEDANG (PENTING)</div>
                                    <h3 class="fw-bold text-warning mb-0"><?= $statAset['total_penting'] ?> Alkes | <?= $statInfra['infra_penting'] ?> Fasilitas</h3>
                                    <div class="small text-muted">Diagnostik, Poliklinik & Rawat Inap</div>
                                </div>
                                <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-circle fs-4">
                                    <i class="bi bi-activity"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card card-stat p-3 border-start border-secondary border-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-secondary small fw-bold text-uppercase">INVESTASI ASET KRITIS</div>
                                    <h4 class="fw-bold text-dark mb-0" style="font-size: 1.25rem;">Rp <?= number_format($statAset['nilai_kritis'], 0, ',', '.') ?></h4>
                                    <div class="small text-muted">Modal alat penyelamat jiwa pasien</div>
                                </div>
                                <div class="bg-secondary bg-opacity-10 text-secondary p-3 rounded-circle fs-4">
                                    <i class="bi bi-cash-coin"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Box -->
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-3">
                    <form method="GET" class="row g-2 align-items-end">
                        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab_aktif) ?>">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-secondary">Tingkat Prioritas Pelayanan</label>
                            <select name="prioritas" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Semua Tingkat Prioritas</option>
                                <option value="Kritis" <?= $filter_prioritas === 'Kritis' ? 'selected' : '' ?>>🚨 Tingkat 1: Kritis (Life Support & Emergency)</option>
                                <option value="Penting" <?= $filter_prioritas === 'Penting' ? 'selected' : '' ?>>⚠️ Tingkat 2: Penting (Diagnostik & Terapi)</option>
                                <option value="Rutin" <?= $filter_prioritas === 'Rutin' ? 'selected' : '' ?>>⚪ Tingkat 3: Rutin (Fasilitas Umum & Kantor)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-secondary">Kategori Aset</label>
                            <select name="kategori" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Semua Kategori</option>
                                <option value="Medis" <?= $filter_kategori === 'Medis' ? 'selected' : '' ?>>Medis (Alat Kesehatan)</option>
                                <option value="Non-Medis" <?= $filter_kategori === 'Non-Medis' ? 'selected' : '' ?>>Non-Medis</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary">Pencarian Aset / Ruangan</label>
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="Ketik nama alat / kode / ruangan..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm flex-fill fw-bold">
                                <i class="bi bi-filter"></i> Filter
                            </button>
                            <?php if (!empty($filter_prioritas) || !empty($filter_kategori) || !empty($search)): ?>
                                <a href="prioritas_pemeliharaan.php?tab=<?= $tab_aktif ?>" class="btn btn-outline-secondary btn-sm" title="Reset Filter">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Tabs Navigation -->
                <ul class="nav nav-tabs mb-3">
                    <li class="nav-item">
                        <a class="nav-link <?= $tab_aktif === 'antrean' ? 'active' : '' ?>" href="?tab=antrean&prioritas=<?= urlencode($filter_prioritas) ?>&kategori=<?= urlencode($filter_kategori) ?>&search=<?= urlencode($search) ?>">
                            <i class="bi bi-list-ol me-1"></i> 1. Antrean Prioritas Pemeliharaan (Queue)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $tab_aktif === 'katalog' ? 'active' : '' ?>" href="?tab=katalog&prioritas=<?= urlencode($filter_prioritas) ?>&kategori=<?= urlencode($filter_kategori) ?>&search=<?= urlencode($search) ?>">
                            <i class="bi bi-heart-pulse me-1"></i> 2. Katalog Aset Kritis & Penunjang Hidup
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $tab_aktif === 'infrastruktur' ? 'active' : '' ?>" href="?tab=infrastruktur&prioritas=<?= urlencode($filter_prioritas) ?>&search=<?= urlencode($search) ?>">
                            <i class="bi bi-building-fill-exclamation me-1"></i> 3. Infrastruktur & Fasilitas Kritis
                        </a>
                    </li>
                </ul>

                <!-- TAB 1: ANTREAN PRIORITAS PEMELIHARAAN (QUEUE) -->
                <?php if ($tab_aktif === 'antrean'): ?>
                    <div class="table-custom">
                        <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-white">
                            <div>
                                <span class="fw-bold text-dark"><i class="bi bi-sort-down text-danger me-2"></i>Antrean Pemeliharaan & Kalibrasi Berdasarkan Tingkat Prioritas</span>
                                <div class="small text-muted">Aset berkategori <strong>Kritis</strong> otomatis ditempatkan di antrean teratas untuk penanganan segera oleh teknisi.</div>
                            </div>
                            <span class="badge bg-danger"><?= count($queueList) ?> Jadwal Terdaftar</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 45px;">No</th>
                                        <th style="width: 140px;">Tingkat Prioritas</th>
                                        <th>Kode & Nama Aset</th>
                                        <th>Lokasi Ruangan</th>
                                        <th>Teknisi / Petugas Kalibrasi</th>
                                        <th class="text-center">Tgl Perawatan</th>
                                        <th class="text-center">Jadwal Kalibrasi Berikutnya</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-end">Biaya Servis</th>
                                        <th class="text-center">SLA Respon</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($queueList)): ?>
                                        <tr><td colspan="10" class="text-center py-4 text-muted">Tidak ada data jadwal antrean pemeliharaan.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($queueList as $idx => $q): ?>
                                            <?php
                                            $isKritis = ($q['tingkat_prioritas'] === 'Kritis');
                                            $isPenting = ($q['tingkat_prioritas'] === 'Penting');
                                            ?>
                                            <tr class="<?= $isKritis ? 'table-danger bg-opacity-10' : ($isPenting ? '' : '') ?>">
                                                <td class="text-secondary"><?= $idx + 1 ?></td>
                                                <td>
                                                    <?php if ($isKritis): ?>
                                                        <span class="badge-kritis"><i class="bi bi-exclamation-triangle-fill"></i> Kritis (Level 1)</span>
                                                    <?php elseif ($isPenting): ?>
                                                        <span class="badge-penting"><i class="bi bi-shield"></i> Penting (Level 2)</span>
                                                    <?php else: ?>
                                                        <span class="badge-rutin"><i class="bi bi-check-circle"></i> Rutin (Level 3)</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge-code"><?= htmlspecialchars($q['kode_aset']) ?></span>
                                                    <div class="fw-bold text-dark mt-1"><?= htmlspecialchars($q['nama_aset']) ?></div>
                                                </td>
                                                <td><i class="bi bi-geo-alt-fill text-danger me-1"></i><?= htmlspecialchars($q['lokasi']) ?></td>
                                                <td>
                                                    <div class="fw-semibold text-primary"><?= htmlspecialchars($q['teknisi'] ?? '-') ?></div>
                                                    <small class="text-muted">Kalibrator: <?= htmlspecialchars($q['petugas_kalibrasi'] ?? '-') ?></small>
                                                </td>
                                                <td class="text-center"><?= date('d/m/Y', strtotime($q['tanggal'])) ?></td>
                                                <td class="text-center">
                                                    <?php if (!empty($q['tanggal_kalibrasi_berikutnya']) && $q['tanggal_kalibrasi_berikutnya'] != '0000-00-00'): ?>
                                                        <span class="fw-bold text-dark"><?= date('d/m/Y', strtotime($q['tanggal_kalibrasi_berikutnya'])) ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($q['status'] === 'Selesai'): ?>
                                                        <span class="badge bg-success">Selesai</span>
                                                    <?php elseif ($q['status'] === 'Sedang Proses'): ?>
                                                        <span class="badge bg-warning text-dark">Sedang Proses</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Belum Dimulai</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end fw-semibold text-dark">
                                                    Rp <?= number_format($q['biaya_pemeliharaan'], 0, ',', '.') ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($isKritis): ?>
                                                        <span class="badge bg-danger text-uppercase px-2 py-1">< 2 Jam</span>
                                                    <?php elseif ($isPenting): ?>
                                                        <span class="badge bg-warning text-dark text-uppercase px-2 py-1">< 24 Jam</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary text-uppercase px-2 py-1">< 48 Jam</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- TAB 2: KATALOG ASET KRITIS & PENUNJANG HIDUP -->
                <?php if ($tab_aktif === 'katalog'): ?>
                    <div class="table-custom">
                        <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-white">
                            <span class="fw-bold text-dark"><i class="bi bi-heart-pulse-fill text-danger me-2"></i>Katalog Seluruh Peralatan Medis & Fasilitas Sesuai Klasifikasi Prioritas</span>
                            <span class="badge bg-secondary"><?= count($katalogList) ?> Peralatan Terdata</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 45px;">No</th>
                                        <th style="width: 140px;">Tingkat Prioritas</th>
                                        <th>Kode & Nama Aset</th>
                                        <th>Kategori & Tipe</th>
                                        <th>Lokasi Ruangan</th>
                                        <th class="text-center">Ketersediaan Unit</th>
                                        <th class="text-end">Harga Satuan</th>
                                        <th>Protokol Pemeliharaan Wajib</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($katalogList as $idx => $kat): ?>
                                        <?php
                                        $prio = $kat['tingkat_prioritas'];
                                        ?>
                                        <tr>
                                            <td class="text-secondary"><?= $idx + 1 ?></td>
                                            <td>
                                                <?php if ($prio === 'Kritis'): ?>
                                                    <span class="badge-kritis"><i class="bi bi-exclamation-triangle-fill"></i> Kritis (Level 1)</span>
                                                <?php elseif ($prio === 'Penting'): ?>
                                                    <span class="badge-penting"><i class="bi bi-shield"></i> Penting (Level 2)</span>
                                                <?php else: ?>
                                                    <span class="badge-rutin"><i class="bi bi-check-circle"></i> Rutin (Level 3)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge-code"><?= htmlspecialchars($kat['kode_aset']) ?></span>
                                                <div class="fw-bold text-dark mt-1"><?= htmlspecialchars($kat['nama_aset']) ?></div>
                                            </td>
                                            <td>
                                                <span class="badge <?= $kat['kategori_aset'] == 'Medis' ? 'bg-danger' : 'bg-primary' ?>"><?= $kat['kategori_aset'] ?></span>
                                                <div class="small text-muted mt-1"><?= htmlspecialchars($kat['tipe_aset']) ?></div>
                                            </td>
                                            <td><i class="bi bi-geo-alt-fill text-danger me-1"></i><?= htmlspecialchars($kat['lokasi']) ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-success">Tersedia: <?= $kat['stok_tersedia'] ?></span>
                                                <?php if ($kat['stok_rusak'] > 0): ?>
                                                    <span class="badge bg-danger">Rusak: <?= $kat['stok_rusak'] ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end fw-semibold text-dark">Rp <?= number_format($kat['harga'], 0, ',', '.') ?></td>
                                            <td class="small">
                                                <?php if ($prio === 'Kritis'): ?>
                                                    <strong class="text-danger">Wajib Kalibrasi 1-2 Bln</strong>, Inspeksi Fisik Harian
                                                <?php elseif ($prio === 'Penting'): ?>
                                                    <span class="text-warning fw-semibold">Pemeliharaan 3-6 Bln</span>, Uji Fungsi Berkala
                                                <?php else: ?>
                                                    <span class="text-secondary">Servis Rutin / Breakdown Maintenance</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- TAB 3: INFRASTRUKTUR & FASILITAS KRITIS -->
                <?php if ($tab_aktif === 'infrastruktur'): ?>
                    <div class="table-custom">
                        <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-white">
                            <span class="fw-bold text-dark"><i class="bi bi-building-fill-exclamation text-danger me-2"></i>Klasifikasi Prioritas Infrastruktur, Gedung & Unit Ruangan</span>
                            <span class="badge bg-secondary"><?= count($infraList) ?> Infrastruktur Terdata</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 45px;">No</th>
                                        <th style="width: 140px;">Tingkat Prioritas</th>
                                        <th>Kode & Nama Infrastruktur</th>
                                        <th>Kategori & Gedung</th>
                                        <th>Lantai / Area</th>
                                        <th>Kondisi Fisik</th>
                                        <th>Penanggung Jawab</th>
                                        <th>Kebijakan Inspeksi Fasilitas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($infraList as $idx => $inf): ?>
                                        <?php
                                        $prioI = $inf['tingkat_prioritas'];
                                        ?>
                                        <tr>
                                            <td class="text-secondary"><?= $idx + 1 ?></td>
                                            <td>
                                                <?php if ($prioI === 'Kritis'): ?>
                                                    <span class="badge-kritis"><i class="bi bi-exclamation-triangle-fill"></i> Kritis (Level 1)</span>
                                                <?php elseif ($prioI === 'Penting'): ?>
                                                    <span class="badge-penting"><i class="bi bi-shield"></i> Penting (Level 2)</span>
                                                <?php else: ?>
                                                    <span class="badge-rutin"><i class="bi bi-check-circle"></i> Rutin (Level 3)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge-code"><?= htmlspecialchars($inf['kode_infrastruktur']) ?></span>
                                                <div class="fw-bold text-dark mt-1"><?= htmlspecialchars($inf['nama_infrastruktur']) ?></div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border"><?= $inf['kategori'] ?></span>
                                                <div class="small text-muted mt-1"><?= htmlspecialchars($inf['nama_gedung']) ?></div>
                                            </td>
                                            <td><?= htmlspecialchars($inf['lantai']) ?> (<?= htmlspecialchars($inf['luas_area']) ?>)</td>
                                            <td>
                                                <span class="badge <?= $inf['kondisi'] == 'Baik' ? 'bg-success' : ($inf['kondisi'] == 'Rusak Ringan' ? 'bg-warning text-dark' : 'bg-danger') ?>">
                                                    <?= $inf['kondisi'] ?>
                                                </span>
                                            </td>
                                            <td class="fw-semibold text-primary"><?= htmlspecialchars($inf['penanggung_jawab']) ?></td>
                                            <td class="small">
                                                <?php if ($prioI === 'Kritis'): ?>
                                                    <strong class="text-danger">Inspeksi Harian & Standby Genset/Gas Medis 24 Jam</strong>
                                                <?php elseif ($prioI === 'Penting'): ?>
                                                    <span class="text-warning fw-semibold">Inspeksi Mingguan & Sanitasi Terjadwal</span>
                                                <?php else: ?>
                                                    <span class="text-secondary">Inspeksi Bulanan & Perawatan Berkala</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
