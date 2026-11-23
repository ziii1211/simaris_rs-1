<?php
session_start();
if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../login.php");
    exit;
}

include(__DIR__ . '/../config/koneksi.php');

$nama_pengguna = $_SESSION['nama_pengguna'] ?? 'Petugas';
$role = $_SESSION['level'] ?? $_SESSION['role'] ?? 'user';

$tahun_aktif = date('Y');
$tahun_filter = isset($_GET['tahun']) && !empty($_GET['tahun']) ? (int)$_GET['tahun'] : (int)$tahun_aktif;
$kategori_filter = isset($_GET['kategori']) ? mysqli_real_escape_string($koneksi, $_GET['kategori']) : '';
$ruangan_filter = isset($_GET['ruangan']) ? mysqli_real_escape_string($koneksi, $_GET['ruangan']) : '';
$tab_aktif = isset($_GET['tab']) ? $_GET['tab'] : 'unit';

// =========================================================================
// 1. QUERY ANALISIS NILAI & KONDISI PER RUANGAN / UNIT
// =========================================================================
$whereUnit = [];
if (!empty($kategori_filter)) {
    $whereUnit[] = "kategori_aset = '$kategori_filter'";
}
if (!empty($ruangan_filter)) {
    $whereUnit[] = "lokasi = '$ruangan_filter'";
}
$whereUnitSql = count($whereUnit) > 0 ? "WHERE " . implode(" AND ", $whereUnit) : "";

$qUnit = mysqli_query($koneksi, "SELECT 
    lokasi,
    COUNT(*) as total_item,
    SUM(total_stok) as total_unit,
    SUM(stok_tersedia) as unit_baik,
    SUM(stok_rusak) as unit_rusak,
    SUM(stok_perawatan) as unit_rawat,
    SUM(harga * total_stok) as total_nilai,
    SUM(CASE WHEN kategori_aset = 'Medis' THEN total_stok ELSE 0 END) as unit_medis,
    SUM(CASE WHEN kategori_aset = 'Non-Medis' THEN total_stok ELSE 0 END) as unit_nonmedis
FROM aset 
$whereUnitSql
GROUP BY lokasi 
ORDER BY total_nilai DESC, lokasi ASC");

$dataRuangan = [];
$totalNilaiRS = 0;
$totalUnitRS = 0;
$totalBaikRS = 0;
$totalRusakRS = 0;
$totalRawatRS = 0;

while ($r = mysqli_fetch_assoc($qUnit)) {
    $dataRuangan[] = $r;
    $totalNilaiRS += (float)$r['total_nilai'];
    $totalUnitRS += (int)$r['total_unit'];
    $totalBaikRS += (int)$r['unit_baik'];
    $totalRusakRS += (int)$r['unit_rusak'];
    $totalRawatRS += (int)$r['unit_rawat'];
}

// =========================================================================
// 2. QUERY FREKUENSI KERUSAKAN & BIAYA PEMELIHARAAN
// =========================================================================
// Tarik data kerusakan agregat
$qKerusakan = mysqli_query($koneksi, "SELECT 
    TRIM(nama_aset) as aset_nama,
    COUNT(*) as frek_rusak,
    SUM(biaya_perbaikan) as total_biaya_rusak
FROM kerusakan 
GROUP BY TRIM(nama_aset)");

$kerusakanMap = [];
$totalBiayaPerbaikan = 0;
while ($k = mysqli_fetch_assoc($qKerusakan)) {
    $kerusakanMap[$k['aset_nama']] = [
        'frek' => (int)$k['frek_rusak'],
        'biaya' => (float)$k['total_biaya_rusak']
    ];
    $totalBiayaPerbaikan += (float)$k['total_biaya_rusak'];
}

// Tarik data perawatan & kalibrasi agregat
$qPerawatan = mysqli_query($koneksi, "SELECT 
    TRIM(nama_aset) as aset_nama,
    COUNT(*) as frek_rawat,
    SUM(biaya_pemeliharaan) as total_biaya_rawat
FROM perawatan 
GROUP BY TRIM(nama_aset)");

$perawatanMap = [];
$totalBiayaPerawatan = 0;
while ($p = mysqli_fetch_assoc($qPerawatan)) {
    $perawatanMap[$p['aset_nama']] = [
        'frek' => (int)$p['frek_rawat'],
        'biaya' => (float)$p['total_biaya_rawat']
    ];
    $totalBiayaPerawatan += (float)$p['total_biaya_rawat'];
}

$totalBiayaMaintRS = $totalBiayaPerbaikan + $totalBiayaPerawatan;

// Gabungkan analisis kerusakan dan pemeliharaan terhadap master data aset
$qAsetMaint = mysqli_query($koneksi, "SELECT 
    id_aset, kode_aset, nama_aset, kategori_aset, jenis, tipe_aset, lokasi, harga, total_stok, umur_ekonomis, YEAR(tanggal_masuk) as thn_masuk
FROM aset 
$whereUnitSql
ORDER BY harga DESC");

$maintAnalysis = [];
while ($a = mysqli_fetch_assoc($qAsetMaint)) {
    $cleanNama = trim($a['nama_aset']);
    $fRusak = $kerusakanMap[$cleanNama]['frek'] ?? 0;
    $bRusak = $kerusakanMap[$cleanNama]['biaya'] ?? 0;
    $fRawat = $perawatanMap[$cleanNama]['frek'] ?? 0;
    $bRawat = $perawatanMap[$cleanNama]['biaya'] ?? 0;
    $totalMaint = $bRusak + $bRawat;

    $totalHargaBeli = (float)$a['harga'] * (int)$a['total_stok'];
    $ratio = ($totalHargaBeli > 0) ? round(($totalMaint / $totalHargaBeli) * 100, 2) : 0;

    $maintAnalysis[] = [
        'id_aset' => $a['id_aset'],
        'kode_aset' => $a['kode_aset'],
        'nama_aset' => $a['nama_aset'],
        'kategori_aset' => $a['kategori_aset'],
        'lokasi' => $a['lokasi'],
        'harga' => (float)$a['harga'],
        'total_stok' => (int)$a['total_stok'],
        'total_harga' => $totalHargaBeli,
        'frek_rusak' => $fRusak,
        'frek_rawat' => $fRawat,
        'biaya_rusak' => $bRusak,
        'biaya_rawat' => $bRawat,
        'total_biaya' => $totalMaint,
        'ratio_biaya' => $ratio,
        'status_ekonomis' => ($ratio > 30) ? 'Tidak Ekonomis' : (($ratio > 10) ? 'Perhatian' : 'Ekonomis')
    ];
}

// Urutkan berdasarkan total biaya pemeliharaan & frekuensi kerusakan tertinggi
usort($maintAnalysis, function ($a, $b) {
    if ($b['frek_rusak'] === $a['frek_rusak']) {
        return $b['total_biaya'] <=> $a['total_biaya'];
    }
    return $b['frek_rusak'] <=> $a['frek_rusak'];
});

// =========================================================================
// 3. QUERY PREDIKSI MASA PENGGANTIAN ASET (END-OF-LIFE FORECASTING)
// =========================================================================
$replacementForecast = [];
$totalCapexReplacement = 0;
$countCritical = 0;

$qAsetForecast = mysqli_query($koneksi, "SELECT 
    id_aset, kode_aset, nama_aset, kategori_aset, jenis, tipe_aset, lokasi, harga, total_stok, stok_tersedia, stok_rusak, stok_perawatan,
    umur_ekonomis, YEAR(tanggal_masuk) as thn_masuk
FROM aset 
$whereUnitSql
ORDER BY thn_masuk ASC");

while ($af = mysqli_fetch_assoc($qAsetForecast)) {
    $thnMasuk = (int)$af['thn_masuk'];
    $umurPakai = max(0, $tahun_filter - $thnMasuk);
    $umurEko = (int)$af['umur_ekonomis'];
    $sisaUmur = $umurEko - $umurPakai;
    $estGanti = (float)$af['harga'] * (int)$af['total_stok'];

    // Status umur ekonomis
    if ($sisaUmur <= 0) {
        $statusMasa = 'Habis Masa Ekonomis';
        $levelPrioritas = 1; // Prioritas Tertinggi
    } elseif ($sisaUmur <= 1) {
        $statusMasa = 'Mendekati Penggantian (<= 1 Thn)';
        $levelPrioritas = 2;
    } else {
        $statusMasa = 'Masih Produktif';
        $levelPrioritas = 3;
    }

    // Jika ada unit rusak berat, tingkatkan prioritas
    if ((int)$af['stok_rusak'] > 0 && $levelPrioritas > 1) {
        $levelPrioritas = 1;
        $statusMasa .= ' (Ada Unit Rusak)';
    }

    if ($levelPrioritas <= 2) {
        $countCritical++;
        $totalCapexReplacement += $estGanti;
    }

    $replacementForecast[] = [
        'id_aset' => $af['id_aset'],
        'kode_aset' => $af['kode_aset'],
        'nama_aset' => $af['nama_aset'],
        'kategori_aset' => $af['kategori_aset'],
        'lokasi' => $af['lokasi'],
        'harga' => (float)$af['harga'],
        'total_stok' => (int)$af['total_stok'],
        'stok_rusak' => (int)$af['stok_rusak'],
        'thn_masuk' => $thnMasuk,
        'umur_pakai' => $umurPakai,
        'umur_ekonomis' => $umurEko,
        'sisa_umur' => $sisaUmur,
        'status_masa' => $statusMasa,
        'prioritas' => $levelPrioritas,
        'estimasi_capex' => $estGanti
    ];
}

// Urutkan aset yang paling mendesak diganti di urutan teratas
usort($replacementForecast, function ($a, $b) {
    if ($a['prioritas'] === $b['prioritas']) {
        return $a['sisa_umur'] <=> $b['sisa_umur'];
    }
    return $a['prioritas'] <=> $b['prioritas'];
});

// Estimasi Anggaran Operasional Pemeliharaan Tahunan (OPEX) Proyeksi (~5% dari total nilai aset aktif)
$totalOpexProjection = $totalNilaiRS * 0.05;

// Ambil list ruangan unik untuk dropdown filter
$qListR = mysqli_query($koneksi, "SELECT DISTINCT lokasi FROM aset WHERE lokasi IS NOT NULL AND lokasi != '' ORDER BY lokasi ASC");
$listRuanganOptions = [];
while ($lr = mysqli_fetch_assoc($qListR)) {
    $listRuanganOptions[] = $lr['lokasi'];
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analisis Aset & Perencanaan Anggaran (DSS) | SIMARIS RS</title>
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

        .card-stat {
            border: none;
            border-radius: 14px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease-in-out;
            background: #fff;
        }

        .card-stat:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.08);
        }

        .filter-card {
            background: #ffffff;
            border-radius: 14px;
            border: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            padding: 16px 20px;
            margin-bottom: 20px;
        }

        .nav-tabs .nav-link {
            font-weight: 600;
            color: #64748b;
            border: none;
            border-bottom: 3px solid transparent;
            padding: 12px 20px;
        }

        .nav-tabs .nav-link.active {
            color: #0d9488;
            border-bottom: 3px solid #0d9488;
            background: transparent;
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

        .progress-slim {
            height: 8px;
            border-radius: 4px;
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
                    <h4 class="fw-bold m-0"><i class="bi bi-graph-up-arrow"></i> ANALISIS ASET & PERENCANAAN ANGGARAN</h4>
                    <div class="small text-white-50">Executive Decision Support System (DSS) untuk Perencanaan CAPEX & OPEX RS Bhayangkara</div>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <a href="cetak_analisis_anggaran.php?tahun=<?= $tahun_filter ?>&kategori=<?= urlencode($kategori_filter) ?>" target="_blank" class="btn btn-light btn-sm fw-bold px-3 rounded-pill">
                        <i class="bi bi-file-earmark-pdf-fill text-danger me-1"></i> Cetak Laporan PDF
                    </a>
                    <a href="export_analisis_excel.php?tahun=<?= $tahun_filter ?>&kategori=<?= urlencode($kategori_filter) ?>" class="btn btn-success btn-sm fw-bold px-3 rounded-pill">
                        <i class="bi bi-file-earmark-excel-fill me-1"></i> Export Excel
                    </a>
                </div>
            </div>

            <div class="content">
                <!-- Filter Bar -->
                <div class="filter-card">
                    <form method="GET" class="row g-3 align-items-end">
                        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab_aktif) ?>">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-secondary">Tahun Proyeksi Anggaran</label>
                            <select name="tahun" class="form-select form-select-sm" onchange="this.form.submit()">
                                <?php for ($y = date('Y') + 2; $y >= 2022; $y--): ?>
                                    <option value="<?= $y ?>" <?= $tahun_filter == $y ? 'selected' : '' ?>>Tahun Anggaran <?= $y ?> <?= $y == date('Y') ? '(Tahun Berjalan)' : '' ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-secondary">Kategori Aset</label>
                            <select name="kategori" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Semua Kategori (Medis & Non-Medis)</option>
                                <option value="Medis" <?= $kategori_filter === 'Medis' ? 'selected' : '' ?>>Medis (Alat Kesehatan)</option>
                                <option value="Non-Medis" <?= $kategori_filter === 'Non-Medis' ? 'selected' : '' ?>>Non-Medis (Fasilitas & IT)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary">Filter Ruangan / Unit</label>
                            <select name="ruangan" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Seluruh Ruangan / Unit Pelayanan</option>
                                <?php foreach ($listRuanganOptions as $rOpt): ?>
                                    <option value="<?= htmlspecialchars($rOpt) ?>" <?= $ruangan_filter === $rOpt ? 'selected' : '' ?>><?= htmlspecialchars($rOpt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">
                                <i class="bi bi-filter"></i> Terapkan
                            </button>
                            <?php if (!empty($kategori_filter) || !empty($ruangan_filter) || $tahun_filter != date('Y')): ?>
                                <a href="analisis_aset.php" class="btn btn-outline-secondary btn-sm" title="Reset Filter">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- 4 Executive KPI Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card card-stat p-3 border-start border-primary border-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-secondary small fw-bold text-uppercase">Total Nilai Investasi Aset</div>
                                    <h4 class="fw-bold text-dark mb-0" style="font-size: 1.25rem;">Rp <?= number_format($totalNilaiRS, 0, ',', '.') ?></h4>
                                    <div class="small text-muted"><?= $totalUnitRS ?> Unit Fisik di <?= count($dataRuangan) ?> Ruangan</div>
                                </div>
                                <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle fs-4">
                                    <i class="bi bi-cash-coin"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card card-stat p-3 border-start border-warning border-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-secondary small fw-bold text-uppercase">Total Biaya Pemeliharaan</div>
                                    <h4 class="fw-bold text-warning mb-0" style="font-size: 1.25rem;">Rp <?= number_format($totalBiayaMaintRS, 0, ',', '.') ?></h4>
                                    <div class="small text-muted">Perbaikan: Rp <?= number_format($totalBiayaPerbaikan, 0, ',', '.') ?> | Servis/Kalibrasi: Rp <?= number_format($totalBiayaPerawatan, 0, ',', '.') ?></div>
                                </div>
                                <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-circle fs-4">
                                    <i class="bi bi-tools"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card card-stat p-3 border-start border-danger border-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-secondary small fw-bold text-uppercase">Aset Kritis / End-of-Life</div>
                                    <h4 class="fw-bold text-danger mb-0" style="font-size: 1.25rem;"><?= $countCritical ?> Aset</h4>
                                    <div class="small text-muted">Habis/Mendekati masa ekonomis (&le; 1 Thn)</div>
                                </div>
                                <div class="bg-danger bg-opacity-10 text-danger p-3 rounded-circle fs-4">
                                    <i class="bi bi-exclamation-octagon-fill"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card card-stat p-3 border-start border-success border-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-secondary small fw-bold text-uppercase">Usulan Anggaran CAPEX <?= $tahun_filter ?></div>
                                    <h4 class="fw-bold text-success mb-0" style="font-size: 1.25rem;">Rp <?= number_format($totalCapexReplacement, 0, ',', '.') ?></h4>
                                    <div class="small text-muted">Estimasi penggantian unit kritis baru</div>
                                </div>
                                <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle fs-4">
                                    <i class="bi bi-piggy-bank-fill"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <ul class="nav nav-tabs mb-3">
                    <li class="nav-item">
                        <a class="nav-link <?= $tab_aktif === 'unit' ? 'active' : '' ?>" href="?tab=unit&tahun=<?= $tahun_filter ?>&kategori=<?= urlencode($kategori_filter) ?>&ruangan=<?= urlencode($ruangan_filter) ?>">
                            <i class="bi bi-building me-1"></i> 1. Nilai & Kondisi per Unit
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $tab_aktif === 'kerusakan' ? 'active' : '' ?>" href="?tab=kerusakan&tahun=<?= $tahun_filter ?>&kategori=<?= urlencode($kategori_filter) ?>&ruangan=<?= urlencode($ruangan_filter) ?>">
                            <i class="bi bi-wrench-adjustable me-1"></i> 2. Frekuensi Rusak & Biaya Servis
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $tab_aktif === 'penggantian' ? 'active' : '' ?>" href="?tab=penggantian&tahun=<?= $tahun_filter ?>&kategori=<?= urlencode($kategori_filter) ?>&ruangan=<?= urlencode($ruangan_filter) ?>">
                            <i class="bi bi-hourglass-bottom me-1"></i> 3. Prediksi Masa Penggantian
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $tab_aktif === 'keputusan' ? 'active' : '' ?>" href="?tab=keputusan&tahun=<?= $tahun_filter ?>&kategori=<?= urlencode($kategori_filter) ?>&ruangan=<?= urlencode($ruangan_filter) ?>">
                            <i class="bi bi-clipboard2-data-fill me-1"></i> 4. Rekomendasi & Perencanaan Anggaran
                        </a>
                    </li>
                </ul>

                <!-- TAB 1: NILAI & KONDISI PER RUANGAN -->
                <?php if ($tab_aktif === 'unit'): ?>
                    <div class="table-custom">
                        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-dark"><i class="bi bi-pie-chart-fill text-primary me-2"></i>Distribusi Nilai Investasi & Rasio Keandalan Fisik per Ruangan</span>
                            <span class="badge bg-light text-dark border"><?= count($dataRuangan) ?> Ruangan Terdata</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">No</th>
                                        <th>Nama Ruangan / Unit Pelayanan</th>
                                        <th class="text-center">Jml Item</th>
                                        <th class="text-center">Total Unit</th>
                                        <th>Kondisi Fisik (Baik / Rusak / Rawat)</th>
                                        <th style="width: 150px;">Indeks Keandalan</th>
                                        <th class="text-end">Total Nilai Aset</th>
                                        <th style="width: 130px;" class="text-center">% Nilai RS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($dataRuangan)): ?>
                                        <tr><td colspan="8" class="text-center py-4 text-muted">Tidak ada data ruangan.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($dataRuangan as $idx => $r): ?>
                                            <?php
                                            $totalU = (int)$r['total_unit'];
                                            $baikU = (int)$r['unit_baik'];
                                            $healthIndex = $totalU > 0 ? round(($baikU / $totalU) * 100, 1) : 0;
                                            $pctRS = $totalNilaiRS > 0 ? round(((float)$r['total_nilai'] / $totalNilaiRS) * 100, 1) : 0;
                                            ?>
                                            <tr>
                                                <td class="text-secondary"><?= $idx + 1 ?></td>
                                                <td>
                                                    <div class="fw-bold text-dark"><?= htmlspecialchars($r['lokasi']) ?></div>
                                                    <div class="small text-muted"><?= $r['unit_medis'] ?> Medis | <?= $r['unit_nonmedis'] ?> Non-Medis</div>
                                                </td>
                                                <td class="text-center"><?= $r['total_item'] ?> Jenis</td>
                                                <td class="text-center fw-bold text-primary"><?= $totalU ?> Unit</td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <span class="badge bg-success" title="Baik / Tersedia">✓ <?= $r['unit_baik'] ?></span>
                                                        <?php if ($r['unit_rusak'] > 0): ?>
                                                            <span class="badge bg-danger" title="Rusak">✕ <?= $r['unit_rusak'] ?></span>
                                                        <?php endif; ?>
                                                        <?php if ($r['unit_rawat'] > 0): ?>
                                                            <span class="badge bg-warning text-dark" title="Perawatan">⚙ <?= $r['unit_rawat'] ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex justify-content-between small fw-semibold mb-1">
                                                        <span><?= $healthIndex ?>%</span>
                                                        <span class="<?= $healthIndex >= 90 ? 'text-success' : ($healthIndex >= 70 ? 'text-warning' : 'text-danger') ?>">
                                                            <?= $healthIndex >= 90 ? 'Prima' : ($healthIndex >= 70 ? 'Waspada' : 'Kritis') ?>
                                                        </span>
                                                    </div>
                                                    <div class="progress progress-slim">
                                                        <div class="progress-bar <?= $healthIndex >= 90 ? 'bg-success' : ($healthIndex >= 70 ? 'bg-warning' : 'bg-danger') ?>" style="width: <?= min(100, $healthIndex) ?>%"></div>
                                                    </div>
                                                </td>
                                                <td class="text-end fw-bold text-dark">Rp <?= number_format($r['total_nilai'], 0, ',', '.') ?></td>
                                                <td class="text-center">
                                                    <span class="badge bg-light text-dark border"><?= $pctRS ?>%</span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- TAB 2: FREKUENSI KERUSAKAN & BIAYA PEMELIHARAAN -->
                <?php if ($tab_aktif === 'kerusakan'): ?>
                    <div class="table-custom">
                        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-dark"><i class="bi bi-tools text-warning me-2"></i>Frekuensi Kerusakan, Beban Biaya Servis & Analisis Rasio Kelayakan Pemeliharaan</span>
                            <span class="badge bg-warning text-dark">Total Biaya Pemeliharaan: Rp <?= number_format($totalBiayaMaintRS, 0, ',', '.') ?></span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">No</th>
                                        <th>Kode & Nama Aset</th>
                                        <th>Lokasi Ruangan</th>
                                        <th class="text-center">Frekuensi Rusak</th>
                                        <th class="text-center">Frekuensi Servis/Kalibrasi</th>
                                        <th class="text-end">Biaya Perbaikan</th>
                                        <th class="text-end">Biaya Kalibrasi</th>
                                        <th class="text-end">Total Biaya Pemeliharaan</th>
                                        <th class="text-center">Rasio thd Harga Aset</th>
                                        <th class="text-center">Status Kelayakan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($maintAnalysis as $idx => $m): ?>
                                        <tr>
                                            <td class="text-secondary"><?= $idx + 1 ?></td>
                                            <td>
                                                <span class="badge-code"><?= htmlspecialchars($m['kode_aset']) ?></span>
                                                <div class="fw-bold text-dark mt-1"><?= htmlspecialchars($m['nama_aset']) ?></div>
                                                <span class="badge <?= $m['kategori_aset'] == 'Medis' ? 'bg-danger' : 'bg-primary' ?> bg-opacity-10 text-<?= $m['kategori_aset'] == 'Medis' ? 'danger' : 'primary' ?>">
                                                    <?= $m['kategori_aset'] ?>
                                                </span>
                                            </td>
                                            <td><i class="bi bi-geo-alt-fill text-danger me-1"></i><?= htmlspecialchars($m['lokasi']) ?></td>
                                            <td class="text-center">
                                                <span class="badge <?= $m['frek_rusak'] >= 2 ? 'bg-danger' : ($m['frek_rusak'] == 1 ? 'bg-warning text-dark' : 'bg-light text-secondary border') ?> px-2 py-1 fs-6">
                                                    <?= $m['frek_rusak'] ?>x
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-info bg-opacity-10 text-info border border-info px-2 py-1">
                                                    <?= $m['frek_rawat'] ?>x
                                                </span>
                                            </td>
                                            <td class="text-end text-secondary">Rp <?= number_format($m['biaya_rusak'], 0, ',', '.') ?></td>
                                            <td class="text-end text-secondary">Rp <?= number_format($m['biaya_rawat'], 0, ',', '.') ?></td>
                                            <td class="text-end fw-bold text-dark">Rp <?= number_format($m['total_biaya'], 0, ',', '.') ?></td>
                                            <td class="text-center fw-bold <?= $m['ratio_biaya'] > 30 ? 'text-danger' : ($m['ratio_biaya'] > 10 ? 'text-warning' : 'text-success') ?>">
                                                <?= $m['ratio_biaya'] ?>%
                                            </td>
                                            <td class="text-center">
                                                <?php if ($m['status_ekonomis'] === 'Tidak Ekonomis'): ?>
                                                    <span class="badge bg-danger">Biaya Servis Tinggi (Ganti Baru)</span>
                                                <?php elseif ($m['status_ekonomis'] === 'Perhatian'): ?>
                                                    <span class="badge bg-warning text-dark">Perlu Monitoring Servis</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Ekonomis Dipertahankan</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- TAB 3: PREDIKSI MASA PENGGANTIAN ASET -->
                <?php if ($tab_aktif === 'penggantian'): ?>
                    <div class="table-custom">
                        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-dark"><i class="bi bi-hourglass-split text-danger me-2"></i>Aset Mendekati / Melampaui Umur Ekonomis (Proyeksi Penggantian Belanja Modal <?= $tahun_filter ?>)</span>
                            <span class="badge bg-danger"><?= $countCritical ?> Unit Aset Kritis Butuh Penggantian</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">No</th>
                                        <th>Kode & Nama Aset</th>
                                        <th>Lokasi Ruangan</th>
                                        <th class="text-center">Tahun Masuk</th>
                                        <th class="text-center">Umur Pakai vs Ekonomis</th>
                                        <th class="text-center">Sisa Umur</th>
                                        <th>Status Kelayakan Teknis</th>
                                        <th class="text-end">Harga Satuan</th>
                                        <th class="text-end">Estimasi Kebutuhan CAPEX</th>
                                        <th class="text-center">Prioritas Penggantian</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($replacementForecast as $idx => $rf): ?>
                                        <tr class="<?= $rf['prioritas'] === 1 ? 'table-danger bg-opacity-25' : ($rf['prioritas'] === 2 ? 'table-warning bg-opacity-25' : '') ?>">
                                            <td class="text-secondary"><?= $idx + 1 ?></td>
                                            <td>
                                                <span class="badge-code"><?= htmlspecialchars($rf['kode_aset']) ?></span>
                                                <div class="fw-bold text-dark mt-1"><?= htmlspecialchars($rf['nama_aset']) ?></div>
                                                <small class="text-muted"><?= $rf['total_stok'] ?> Unit Fisik</small>
                                            </td>
                                            <td><i class="bi bi-geo-alt-fill text-danger me-1"></i><?= htmlspecialchars($rf['lokasi']) ?></td>
                                            <td class="text-center"><?= $rf['thn_masuk'] ?></td>
                                            <td class="text-center fw-semibold">
                                                <?= $rf['umur_pakai'] ?> Thn / <?= $rf['umur_ekonomis'] ?> Thn
                                            </td>
                                            <td class="text-center fw-bold <?= $rf['sisa_umur'] <= 0 ? 'text-danger' : ($rf['sisa_umur'] <= 1 ? 'text-warning' : 'text-success') ?>">
                                                <?= $rf['sisa_umur'] ?> Tahun
                                            </td>
                                            <td>
                                                <span class="badge <?= $rf['sisa_umur'] <= 0 ? 'bg-danger' : ($rf['sisa_umur'] <= 1 ? 'bg-warning text-dark' : 'bg-success') ?>">
                                                    <?= $rf['status_masa'] ?>
                                                </span>
                                            </td>
                                            <td class="text-end text-muted">Rp <?= number_format($rf['harga'], 0, ',', '.') ?></td>
                                            <td class="text-end fw-bold text-dark">Rp <?= number_format($rf['estimasi_capex'], 0, ',', '.') ?></td>
                                            <td class="text-center">
                                                <?php if ($rf['prioritas'] === 1): ?>
                                                    <span class="badge bg-danger text-uppercase px-2 py-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Prioritas 1 (Mendesak)</span>
                                                <?php elseif ($rf['prioritas'] === 2): ?>
                                                    <span class="badge bg-warning text-dark text-uppercase px-2 py-1">Prioritas 2 (Tahun <?= $tahun_filter ?>)</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success text-uppercase px-2 py-1">Prioritas 3 (Aman)</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- TAB 4: REKOMENDASI PENGAMBILAN KEPUTUSAN & ANGGARAN -->
                <?php if ($tab_aktif === 'keputusan'): ?>
                    <div class="row g-4">
                        <!-- Kotak Simulasi Anggaran CAPEX & OPEX -->
                        <div class="col-lg-5">
                            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-calculator-fill text-primary me-2"></i>Simulasi Alokasi Anggaran RS (Tahun <?= $tahun_filter ?>)</h5>
                                <p class="small text-secondary mb-4">
                                    Berdasarkan rekam jejak historis depresiasi umur ekonomis, frekuensi kerusakan, dan biaya pemeliharaan berkala SIMARIS RS.
                                </p>

                                <div class="p-3 rounded-3 bg-danger bg-opacity-10 border border-danger mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold text-danger"><i class="bi bi-cart-plus-fill me-1"></i> Belanja Modal Penggantian (CAPEX)</span>
                                        <span class="badge bg-danger"><?= $countCritical ?> Alat Kritis</span>
                                    </div>
                                    <h3 class="fw-bold text-danger mb-1">Rp <?= number_format($totalCapexReplacement, 0, ',', '.') ?></h3>
                                    <div class="small text-secondary">Alokasi untuk pengadaan alat baru pengganti unit yang habis masa ekonomis & sering rusak.</div>
                                </div>

                                <div class="p-3 rounded-3 bg-warning bg-opacity-10 border border-warning mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold text-warning"><i class="bi bi-wrench me-1"></i> Belanja Operasional Pemeliharaan (OPEX)</span>
                                        <span class="badge bg-warning text-dark">Rekomendasi 5% Aset</span>
                                    </div>
                                    <h3 class="fw-bold text-warning mb-1">Rp <?= number_format($totalOpexProjection, 0, ',', '.') ?></h3>
                                    <div class="small text-secondary">Alokasi biaya servis berkala, suku cadang, dan kalibrasi tahunan untuk menjaga keandalan fasilitas.</div>
                                </div>

                                <div class="p-3 rounded-3 bg-success bg-opacity-10 border border-success">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold text-success"><i class="bi bi-cash-stack me-1"></i> Total Usulan Anggaran Aset RS</span>
                                        <span class="badge bg-success">Total CAPEX + OPEX</span>
                                    </div>
                                    <h3 class="fw-bold text-success mb-1">Rp <?= number_format($totalCapexReplacement + $totalOpexProjection, 0, ',', '.') ?></h3>
                                    <div class="small text-secondary">Estimasi total kebutuhan anggaran sarana & prasarana tahun <?= $tahun_filter ?>.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Matriks Rekomendasi Manajerial Otomatis -->
                        <div class="col-lg-7">
                            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-lightbulb-fill text-warning me-2"></i>Rekomendasi Keputusan Manajerial (Decision Matrix)</h5>
                                <div class="d-flex flex-column gap-3">
                                    <!-- Rekomendasi 1: Penggantian Alat Kritis -->
                                    <div class="p-3 rounded-3 border-start border-danger border-4 bg-light">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <span class="badge bg-danger text-uppercase">Tindakan Prioritas 1: Penggantian Baru</span>
                                            <span class="small text-muted fw-semibold">Urgensi Tinggi</span>
                                        </div>
                                        <h6 class="fw-bold text-dark mb-1">Penggantian Aset Usang & Rusak di Unit Pelayanan Kritis</h6>
                                        <p class="small text-secondary mb-2">
                                            Terdapat <strong><?= $countCritical ?> item aset</strong> yang telah melampaui umur ekonomisnya atau mengalami biaya perbaikan tinggi (di atas 30% harga perolehan). Disarankan segera memasukkan ke dalam usulan Rencana Bisnis dan Anggaran (RBA) pengadaan modal belanja tahun <?= $tahun_filter ?> agar tidak mengganggu operasional pelayanan medis pasien.
                                        </p>
                                    </div>

                                    <!-- Rekomendasi 2: Pemeliharaan Preventif Terjadwal -->
                                    <div class="p-3 rounded-3 border-start border-warning border-4 bg-light">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <span class="badge bg-warning text-dark text-uppercase">Tindakan Prioritas 2: Pemeliharaan Preventif</span>
                                            <span class="small text-muted fw-semibold">Urgensi Sedang</span>
                                        </div>
                                        <h6 class="fw-bold text-dark mb-1">Perketat Kalibrasi dan Servis Berkala pada Alkes Bernilai Tinggi</h6>
                                        <p class="small text-secondary mb-2">
                                            Aset medis vital seperti Bed Side Monitor, Alat USG, dan Mesin EKG disarankan menjalani kalibrasi rutin sebelum tanggal jatuh tempo guna menekan frekuensi insiden kerusakan tidak terduga dan memperpanjang masa pakai alat secara signifikan.
                                        </p>
                                    </div>

                                    <!-- Rekomendasi 3: Pengawasan Aset Sebaran Ruangan -->
                                    <div class="p-3 rounded-3 border-start border-success border-4 bg-light">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <span class="badge bg-success text-uppercase">Tindakan Prioritas 3: Optimalisasi & Pelacakan</span>
                                            <span class="small text-muted fw-semibold">Monitoring Rutin</span>
                                        </div>
                                        <h6 class="fw-bold text-dark mb-1">Pemeriksaan Fisik Berbasis Scan QR & Pembaruan KIR Tiap Semester</h6>
                                        <p class="small text-secondary mb-0">
                                            Gunakan pemindai kamera QR Code SIMARIS untuk pemeriksaan fisik berkala di seluruh ruangan dan pastikan Kartu Inventaris Ruangan (KIR) selalu ditandatangani oleh Penanggung Jawab Ruangan dan Kepala Rumah Sakit demi kepatuhan akuntabilitas aset BMN / Rumah Sakit Polri.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
