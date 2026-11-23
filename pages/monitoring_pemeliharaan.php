<?php
session_start();
if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../login.php");
    exit;
}

include(__DIR__ . '/../config/koneksi.php');

$nama_pengguna = $_SESSION['nama_pengguna'] ?? 'Petugas';
$role = $_SESSION['level'] ?? $_SESSION['role'] ?? 'user';

// Handle submit checklist pemantauan kondisi cepat dari modal
$alertMsg = '';
$alertType = '';
if (isset($_POST['simpan_checklist'])) {
    $id_aset_check = (int)$_POST['id_aset'];
    $kondisi_baru  = mysqli_real_escape_string($koneksi, $_POST['kondisi']);
    $catatan_check = mysqli_real_escape_string($koneksi, trim($_POST['catatan_pemantauan']));
    $pemeriksa     = mysqli_real_escape_string($koneksi, trim($_POST['pemeriksa'] ?? $nama_pengguna));
    $tgl_next_maint= !empty($_POST['jadwal_maintenance_berikutnya']) ? mysqli_real_escape_string($koneksi, $_POST['jadwal_maintenance_berikutnya']) : null;

    $nextSql = $tgl_next_maint ? ", jadwal_maintenance_berikutnya = '$tgl_next_maint'" : "";

    $updAset = mysqli_query($koneksi, "UPDATE aset SET 
        kondisi = '$kondisi_baru'
        $nextSql
        WHERE id_aset = $id_aset_check");

    // Catat ke audit_fisik sebagai rekam jejak inspeksi berkala
    $inAudit = mysqli_query($koneksi, "INSERT INTO audit_fisik (id_aset, tanggal_audit, kondisi_fisik, keterangan, auditor) 
        VALUES ($id_aset_check, NOW(), '$kondisi_baru', '$catatan_check', '$pemeriksa')");

    if ($updAset) {
        $alertMsg = "Hasil pemantauan berkala aset berhasil disimpan ke dalam buku riwayat!";
        $alertType = "success";
    } else {
        $alertMsg = "Gagal memperbarui pemantauan: " . mysqli_error($koneksi);
        $alertType = "danger";
    }
}

// Filter Parameter
$search           = isset($_GET['search']) ? mysqli_real_escape_string($koneksi, trim($_GET['search'])) : '';
$filter_kategori  = isset($_GET['kategori']) ? mysqli_real_escape_string($koneksi, $_GET['kategori']) : '';
$filter_prioritas = isset($_GET['prioritas']) ? mysqli_real_escape_string($koneksi, $_GET['prioritas']) : '';
$filter_kondisi   = isset($_GET['kondisi']) ? mysqli_real_escape_string($koneksi, $_GET['kondisi']) : '';
$filter_jadwal    = isset($_GET['jadwal']) ? mysqli_real_escape_string($koneksi, $_GET['jadwal']) : '';

// 1. STATISTIK KESELURUHAN MONITORING KONDISI & PEMELIHARAAN
$qStat = mysqli_query($koneksi, "SELECT 
    COUNT(*) as total_aset,
    SUM(stok_tersedia) as total_unit_tersedia,
    SUM(total_stok) as total_unit_keseluruhan,
    SUM(stok_rusak) as total_unit_rusak,
    SUM(stok_perawatan) as total_unit_perawatan,
    SUM(CASE WHEN tingkat_prioritas = 'Kritis' THEN 1 ELSE 0 END) as total_kritis,
    SUM(CASE WHEN DATEDIFF(jadwal_maintenance_berikutnya, CURDATE()) <= 7 AND DATEDIFF(jadwal_maintenance_berikutnya, CURDATE()) >= 0 THEN 1 ELSE 0 END) as jatuh_tempo_segera,
    SUM(CASE WHEN DATEDIFF(jadwal_maintenance_berikutnya, CURDATE()) < 0 THEN 1 ELSE 0 END) as terlewat_jadwal
FROM aset");
$stat = mysqli_fetch_assoc($qStat);

$totalUnitSemua = (int)($stat['total_unit_keseluruhan'] ?? 1);
$totalUnitBagus = (int)($stat['total_unit_tersedia'] ?? 0);
$healthIndex = $totalUnitSemua > 0 ? round(($totalUnitBagus / $totalUnitSemua) * 100, 1) : 100;

// Hitung total kerusakan teratasi (tindakan perbaikan selesai)
$qKPerbaikan = mysqli_query($koneksi, "SELECT 
    COUNT(*) as total_kerusakan,
    SUM(status IN ('Selesai Diperbaiki', 'Selesai')) as selesai_perbaikan,
    SUM(biaya_perbaikan) as total_biaya_perbaikan
FROM kerusakan");
$rKPerbaikan = mysqli_fetch_assoc($qKPerbaikan);
$totalKasusRusak = (int)($rKPerbaikan['total_kerusakan'] ?? 0);
$selesaiPerbaikan = (int)($rKPerbaikan['selesai_perbaikan'] ?? 0);
$complianceRate = $totalKasusRusak > 0 ? round(($selesaiPerbaikan / $totalKasusRusak) * 100, 1) : 100;

// 2. QUERY DAFTAR ASET TERPANTAU
$where = [];
if (!empty($search)) {
    $where[] = "(a.nama_aset LIKE '%$search%' OR a.kode_aset LIKE '%$search%' OR a.lokasi LIKE '%$search%' OR a.pj_ruangan LIKE '%$search%')";
}
if (!empty($filter_kategori)) {
    $where[] = "a.kategori_aset = '$filter_kategori'";
}
if (!empty($filter_prioritas)) {
    $where[] = "a.tingkat_prioritas = '$filter_prioritas'";
}
if (!empty($filter_kondisi)) {
    if ($filter_kondisi === 'Baik') {
        $where[] = "a.stok_rusak = 0 AND a.stok_perawatan = 0";
    } elseif ($filter_kondisi === 'Rusak') {
        $where[] = "a.stok_rusak > 0";
    } elseif ($filter_kondisi === 'Perawatan') {
        $where[] = "a.stok_perawatan > 0";
    }
}
if (!empty($filter_jadwal)) {
    if ($filter_jadwal === 'segera') {
        $where[] = "DATEDIFF(a.jadwal_maintenance_berikutnya, CURDATE()) <= 7 AND DATEDIFF(a.jadwal_maintenance_berikutnya, CURDATE()) >= 0";
    } elseif ($filter_jadwal === 'terlewat') {
        $where[] = "DATEDIFF(a.jadwal_maintenance_berikutnya, CURDATE()) < 0";
    } elseif ($filter_jadwal === 'aman') {
        $where[] = "DATEDIFF(a.jadwal_maintenance_berikutnya, CURDATE()) > 7";
    }
}

$whereSql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

// Join dengan riwayat kerusakan terakhir & pemeliharaan terakhir
$qAsetList = mysqli_query($koneksi, "SELECT 
    a.*,
    (SELECT k.keterangan FROM kerusakan k WHERE TRIM(k.nama_aset) COLLATE utf8mb4_general_ci = TRIM(a.nama_aset) COLLATE utf8mb4_general_ci ORDER BY k.id DESC LIMIT 1) as keluhan_rusak_terakhir,
    (SELECT k.tindakan_perbaikan FROM kerusakan k WHERE TRIM(k.nama_aset) COLLATE utf8mb4_general_ci = TRIM(a.nama_aset) COLLATE utf8mb4_general_ci ORDER BY k.id DESC LIMIT 1) as tindakan_perbaikan_terakhir,
    (SELECT k.sparepart_diganti FROM kerusakan k WHERE TRIM(k.nama_aset) COLLATE utf8mb4_general_ci = TRIM(a.nama_aset) COLLATE utf8mb4_general_ci ORDER BY k.id DESC LIMIT 1) as sparepart_terakhir,
    (SELECT k.teknisi FROM kerusakan k WHERE TRIM(k.nama_aset) COLLATE utf8mb4_general_ci = TRIM(a.nama_aset) COLLATE utf8mb4_general_ci ORDER BY k.id DESC LIMIT 1) as teknisi_perbaikan,
    (SELECT k.tanggal FROM kerusakan k WHERE TRIM(k.nama_aset) COLLATE utf8mb4_general_ci = TRIM(a.nama_aset) COLLATE utf8mb4_general_ci ORDER BY k.id DESC LIMIT 1) as tgl_rusak_terakhir,
    (SELECT k.status FROM kerusakan k WHERE TRIM(k.nama_aset) COLLATE utf8mb4_general_ci = TRIM(a.nama_aset) COLLATE utf8mb4_general_ci ORDER BY k.id DESC LIMIT 1) as status_rusak_terakhir,
    (SELECT p.teknisi FROM perawatan p WHERE TRIM(p.nama_aset) COLLATE utf8mb4_general_ci = TRIM(a.nama_aset) COLLATE utf8mb4_general_ci ORDER BY p.id DESC LIMIT 1) as teknisi_pemeliharaan,
    (SELECT p.petugas_kalibrasi FROM perawatan p WHERE TRIM(p.nama_aset) COLLATE utf8mb4_general_ci = TRIM(a.nama_aset) COLLATE utf8mb4_general_ci ORDER BY p.id DESC LIMIT 1) as petugas_kalibrasi,
    (SELECT p.tanggal_kalibrasi_berikutnya FROM perawatan p WHERE TRIM(p.nama_aset) COLLATE utf8mb4_general_ci = TRIM(a.nama_aset) COLLATE utf8mb4_general_ci ORDER BY p.id DESC LIMIT 1) as tgl_kalibrasi_p
FROM aset a
$whereSql
ORDER BY FIELD(a.tingkat_prioritas, 'Kritis', 'Penting', 'Rutin'), a.nama_aset ASC");

$asetData = [];
while ($row = mysqli_fetch_assoc($qAsetList)) {
    // Sinkronisasi tanggal maintenance berikutnya jika belum ada di tabel aset tapi ada di perawatan
    if (empty($row['jadwal_maintenance_berikutnya']) && !empty($row['tgl_kalibrasi_p'])) {
        $row['jadwal_maintenance_berikutnya'] = $row['tgl_kalibrasi_p'];
    }
    $asetData[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Kondisi & Pemeliharaan Aset | SIMARIS RS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto+Mono:wght@600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            margin: 0;
        }

        #wrapper {
            display: flex;
            min-height: 100vh;
        }

        #page-content-wrapper {
            flex: 1;
            padding: 0;
            background-color: #f8fafc;
            overflow-x: hidden;
        }

        .dashboard-header {
            background: linear-gradient(135deg, #1e3a8a, #0d9488);
            color: #fff;
            padding: 22px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid rgba(255, 255, 255, 0.2);
        }

        .content {
            padding: 30px;
        }

        .card-stat {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            height: 100%;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .card-stat:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 12px;
        }

        .stat-val {
            font-size: 1.8rem;
            font-weight: 700;
            line-height: 1.2;
            color: #0f172a;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 500;
        }

        .stat-sub {
            font-size: 0.75rem;
            margin-top: 6px;
        }

        .table-custom {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .table-custom table {
            font-size: 0.85rem;
            margin-bottom: 0;
        }

        .table-custom thead th {
            background: #f1f5f9;
            color: #334155;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 0.5px;
            padding: 12px 14px;
            border-bottom: 2px solid #cbd5e1;
        }

        .table-custom tbody td {
            padding: 12px 14px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }

        .badge-code {
            font-family: 'Roboto Mono', monospace;
            font-size: 0.75rem;
            padding: 3px 7px;
            border-radius: 6px;
            background: #0f172a;
            color: #38bdf8;
            font-weight: 600;
            text-decoration: none;
        }

        .badge-kritis {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
            font-size: 0.72rem;
            padding: 3px 8px;
            border-radius: 20px;
            font-weight: 600;
        }

        .badge-penting {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
            font-size: 0.72rem;
            padding: 3px 8px;
            border-radius: 20px;
            font-weight: 600;
        }

        .badge-rutin {
            background-color: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
            font-size: 0.72rem;
            padding: 3px 8px;
            border-radius: 20px;
            font-weight: 600;
        }

        .badge-countdown {
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.75rem;
            display: inline-block;
        }

        .countdown-terlewat {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #f87171;
            animation: pulse-danger 2s infinite;
        }

        .countdown-segera {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fbbf24;
        }

        .countdown-aman {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
        }

        @keyframes pulse-danger {
            0% { opacity: 1; }
            50% { opacity: 0.6; }
            100% { opacity: 1; }
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
            <!-- Top Dashboard Header -->
            <div class="dashboard-header">
                <div>
                    <h4 class="fw-bold m-0"><i class="bi bi-activity me-2"></i> MONITORING KONDISI & PEMELIHARAAN ASET</h4>
                    <small style="opacity: 0.9;">Pemantauan Berkala Kondisi Fisik, Jadwal Maintenance, Tindakan Perbaikan, dan Penanggung Jawab Alkes & Sarpras</small>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <a href="cetak_monitoring_pemeliharaan.php?prioritas=<?= urlencode($filter_prioritas) ?>&kategori=<?= urlencode($filter_kategori) ?>&search=<?= urlencode($search) ?>" target="_blank" class="btn btn-danger btn-sm shadow-sm">
                        <i class="bi bi-file-earmark-pdf-fill me-1"></i> Cetak Laporan Monitoring PDF
                    </a>
                    <a href="prioritas_pemeliharaan.php" class="btn btn-light btn-sm fw-bold text-dark shadow-sm">
                        <i class="bi bi-shield-fill-check text-warning me-1"></i> Dashboard Kritis
                    </a>
                </div>
            </div>

            <div class="content">

                <?php if (!empty($alertMsg)): ?>
                    <div class="alert alert-<?= $alertType ?> alert-dismissible fade show mb-4 shadow-sm" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i> <?= $alertMsg ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- 1. EXECUTIVE KPI STAT CARDS -->
                <div class="row g-3 mb-4">
                    <!-- Stat 1: Total Aset Terpantau -->
                    <div class="col-lg-3 col-md-6">
                        <div class="card-stat">
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-hospital"></i>
                            </div>
                            <div class="stat-val"><?= $stat['total_aset'] ?> <span class="fs-6 text-muted fw-normal">Jenis</span></div>
                            <div class="stat-label">Total Aset Terpantau Berkala</div>
                            <div class="stat-sub text-muted">Mencakup <?= $stat['total_unit_keseluruhan'] ?> total unit fisik aktif di RS</div>
                        </div>
                    </div>

                    <!-- Stat 2: Health Index / Kondisi Prima -->
                    <div class="col-lg-3 col-md-6">
                        <div class="card-stat">
                            <div class="stat-icon bg-success bg-opacity-10 text-success">
                                <i class="bi bi-heart-pulse-fill"></i>
                            </div>
                            <div class="stat-val text-success"><?= $healthIndex ?>%</div>
                            <div class="stat-label">Tingkat Kesiapan / Health Index</div>
                            <div class="stat-sub text-success">
                                <i class="bi bi-check-circle me-1"></i> <?= $stat['total_unit_tersedia'] ?> unit dalam kondisi prima & siap pakai
                            </div>
                        </div>
                    </div>

                    <!-- Stat 3: Jatuh Tempo Servis & Terlewat -->
                    <div class="col-lg-3 col-md-6">
                        <div class="card-stat">
                            <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                <i class="bi bi-calendar-event"></i>
                            </div>
                            <div class="stat-val text-warning">
                                <?= $stat['jatuh_tempo_segera'] ?> <span class="fs-6 text-danger fw-semibold">(+<?= $stat['terlewat_jadwal'] ?> Overdue)</span>
                            </div>
                            <div class="stat-label">Maintenance H-7 / Jatuh Tempo</div>
                            <div class="stat-sub text-danger">
                                <i class="bi bi-exclamation-triangle me-1"></i> Perlu tindakan kalibrasi & servis preventif segera
                            </div>
                        </div>
                    </div>

                    <!-- Stat 4: Kepatuhan Tindakan Perbaikan (Compliance) -->
                    <div class="col-lg-3 col-md-6">
                        <div class="card-stat">
                            <div class="stat-icon bg-teal bg-opacity-10 text-dark">
                                <i class="bi bi-tools"></i>
                            </div>
                            <div class="stat-val text-primary"><?= $complianceRate ?>%</div>
                            <div class="stat-label">Kepatuhan Perbaikan (Resolution)</div>
                            <div class="stat-sub text-muted">
                                <?= $selesaiPerbaikan ?> dari <?= $totalKasusRusak ?> insiden kerusakan tuntas ditangani
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. FILTER & SEARCH BAR -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-3">
                        <form method="GET" class="row g-2 align-items-center">
                            <div class="col-md-3">
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="🔍 Cari kode, nama aset, ruangan, teknisi..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-2">
                                <select name="kategori" class="form-select form-select-sm">
                                    <option value="">-- Semua Kategori --</option>
                                    <option value="Medis" <?= ($filter_kategori === 'Medis') ? 'selected' : '' ?>>Medis (Alkes)</option>
                                    <option value="Non-Medis" <?= ($filter_kategori === 'Non-Medis') ? 'selected' : '' ?>>Non-Medis (Sarpras)</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="prioritas" class="form-select form-select-sm">
                                    <option value="">-- Semua Prioritas --</option>
                                    <option value="Kritis" <?= ($filter_prioritas === 'Kritis') ? 'selected' : '' ?>>🔴 Kritis (Life-Support)</option>
                                    <option value="Penting" <?= ($filter_prioritas === 'Penting') ? 'selected' : '' ?>>🟡 Penting (Diagnostik)</option>
                                    <option value="Rutin" <?= ($filter_prioritas === 'Rutin') ? 'selected' : '' ?>>⚪ Rutin (Fasilitas Umum)</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="kondisi" class="form-select form-select-sm">
                                    <option value="">-- Status Kondisi --</option>
                                    <option value="Baik" <?= ($filter_kondisi === 'Baik') ? 'selected' : '' ?>>Semua Unit Siap Pakai (Baik)</option>
                                    <option value="Rusak" <?= ($filter_kondisi === 'Rusak') ? 'selected' : '' ?>>Ada Unit Rusak</option>
                                    <option value="Perawatan" <?= ($filter_kondisi === 'Perawatan') ? 'selected' : '' ?>>Ada Unit Servis/Kalibrasi</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="jadwal" class="form-select form-select-sm">
                                    <option value="">-- Status Jadwal --</option>
                                    <option value="segera" <?= ($filter_jadwal === 'segera') ? 'selected' : '' ?>>Jatuh Tempo H-7</option>
                                    <option value="terlewat" <?= ($filter_jadwal === 'terlewat') ? 'selected' : '' ?>>Terlewat (Overdue)</option>
                                    <option value="aman" <?= ($filter_jadwal === 'aman') ? 'selected' : '' ?>>Jadwal Masih Panjang</option>
                                </select>
                            </div>
                            <div class="col-md-1 d-flex gap-1">
                                <button type="submit" class="btn btn-sm btn-primary flex-fill" title="Terapkan Filter"><i class="bi bi-filter"></i></button>
                                <a href="monitoring_pemeliharaan.php" class="btn btn-sm btn-outline-secondary" title="Reset Filter"><i class="bi bi-arrow-counterclockwise"></i></a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 3. TABEL MASTER MONITORING KONDISI & PEMELIHARAAN -->
                <div class="table-custom">
                    <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-white">
                        <div>
                            <span class="fw-bold text-dark"><i class="bi bi-card-checklist text-primary me-2"></i>Matriks Monitoring Kondisi Fisik, Jadwal Kalibrasi & Rekam Jejak Servis</span>
                            <div class="small text-muted">Pemantauan real-time status kelayakan peralatan rumah sakit dan riwayat penanganan tindakan perbaikan teknisi.</div>
                        </div>
                        <span class="badge bg-primary px-3 py-2"><?= count($asetData) ?> Aset Terdata</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 35px;">No</th>
                                    <th style="width: 140px;">Prioritas & Kode</th>
                                    <th>Nama Aset / Peralatan</th>
                                    <th>Lokasi & Penanggung Jawab</th>
                                    <th class="text-center" style="width: 130px;">Kondisi Fisik</th>
                                    <th class="text-center" style="width: 160px;">Jadwal Maintenance</th>
                                    <th style="min-width: 220px;">Riwayat Kerusakan & Tindakan Perbaikan</th>
                                    <th style="width: 150px;">Pihak Bertanggung Jawab</th>
                                    <th class="text-center" style="width: 120px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($asetData)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-5 text-muted">
                                            <i class="bi bi-inboxes text-secondary d-block mb-2 fs-2"></i>
                                            Tidak ada aset yang sesuai kriteria pencarian monitoring.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($asetData as $idx => $a): ?>
                                        <?php
                                        $prio = $a['tingkat_prioritas'] ?? 'Penting';
                                        $isKritis = ($prio === 'Kritis');

                                        // Hitung countdown jadwal maintenance
                                        $tglNext = $a['jadwal_maintenance_berikutnya'];
                                        $countdownStr = '-';
                                        $countdownClass = 'countdown-aman';
                                        if (!empty($tglNext) && $tglNext != '0000-00-00') {
                                            $diffSec = strtotime($tglNext) - strtotime('today');
                                            $diffDay = floor($diffSec / (60 * 60 * 24));
                                            if ($diffDay < 0) {
                                                $lewat = abs($diffDay);
                                                $countdownStr = "Terlewat $lewat Hari!";
                                                $countdownClass = 'countdown-terlewat';
                                            } elseif ($diffDay == 0) {
                                                $countdownStr = "Hari Ini (Due Today!)";
                                                $countdownClass = 'countdown-terlewat';
                                            } elseif ($diffDay <= 7) {
                                                $countdownStr = "H-$diffDay Jatuh Tempo";
                                                $countdownClass = 'countdown-segera';
                                            } else {
                                                $countdownStr = "$diffDay Hari Lagi";
                                                $countdownClass = 'countdown-aman';
                                            }
                                        }

                                        // Evaluasi Kondisi Fisik
                                        $stokT = (int)$a['total_stok'];
                                        $stokB = (int)$a['stok_tersedia'];
                                        $stokR = (int)$a['stok_rusak'];
                                        $stokP = (int)$a['stok_perawatan'];
                                        ?>
                                        <tr style="<?= $isKritis ? 'background-color: #fff9f9;' : '' ?>">
                                            <td class="text-center fw-semibold text-secondary"><?= $idx + 1 ?></td>
                                            
                                            <!-- Prioritas & Kode -->
                                            <td>
                                                <?php if ($prio === 'Kritis'): ?>
                                                    <span class="badge-kritis mb-1 d-inline-block"><i class="bi bi-exclamation-triangle-fill"></i> Kritis</span>
                                                <?php elseif ($prio === 'Penting'): ?>
                                                    <span class="badge-penting mb-1 d-inline-block"><i class="bi bi-shield"></i> Penting</span>
                                                <?php else: ?>
                                                    <span class="badge-rutin mb-1 d-inline-block"><i class="bi bi-check-circle"></i> Rutin</span>
                                                <?php endif; ?>
                                                <div>
                                                    <a href="detail_aset_qr.php?kode=<?= urlencode($a['kode_aset']) ?>" class="badge-code" title="Lihat Detail QR & Verifikasi">
                                                        <?= htmlspecialchars($a['kode_aset']) ?>
                                                    </a>
                                                </div>
                                            </td>

                                            <!-- Nama Aset -->
                                            <td>
                                                <a href="logbook_aset.php?id=<?= $a['id_aset'] ?>" class="text-dark fw-bold text-decoration-none hover-primary">
                                                    <?= htmlspecialchars($a['nama_aset']) ?>
                                                </a>
                                                <div class="small text-muted">
                                                    Kategori: <span class="fw-semibold text-secondary"><?= htmlspecialchars($a['kategori_aset']) ?></span> | Merek/Tipe: <?= htmlspecialchars($a['tipe_aset'] ?? '-') ?>
                                                </div>
                                            </td>

                                            <!-- Lokasi & PJ Ruangan -->
                                            <td>
                                                <div class="fw-semibold text-dark"><i class="bi bi-geo-alt-fill text-danger me-1"></i><?= htmlspecialchars($a['lokasi']) ?></div>
                                                <div class="small text-muted mt-1">
                                                    <i class="bi bi-person-badge text-primary me-1"></i>PJ: <?= htmlspecialchars($a['pj_ruangan'] ?? 'Kepala Ruangan') ?>
                                                </div>
                                            </td>

                                            <!-- Kondisi Fisik -->
                                            <td class="text-center">
                                                <?php if ($stokR > 0): ?>
                                                    <span class="badge bg-danger mb-1 d-block">Rusak: <?= $stokR ?> Unit</span>
                                                <?php endif; ?>
                                                <?php if ($stokP > 0): ?>
                                                    <span class="badge bg-warning text-dark mb-1 d-block">Servis: <?= $stokP ?> Unit</span>
                                                <?php endif; ?>
                                                <?php if ($stokR == 0 && $stokP == 0): ?>
                                                    <span class="badge bg-success mb-1 d-block">100% Siap Pakai</span>
                                                <?php endif; ?>
                                                <div class="small text-muted" style="font-size: 11px;">
                                                    Tersedia <?= $stokB ?> dari <?= $stokT ?> Unit
                                                </div>
                                            </td>

                                            <!-- Jadwal Maintenance -->
                                            <td class="text-center">
                                                <div class="fw-bold text-dark mb-1">
                                                    <?= !empty($tglNext) && $tglNext != '0000-00-00' ? date('d-m-Y', strtotime($tglNext)) : '-' ?>
                                                </div>
                                                <span class="badge-countdown <?= $countdownClass ?>">
                                                    <i class="bi bi-clock-history me-1"></i><?= $countdownStr ?>
                                                </span>
                                            </td>

                                            <!-- Riwayat Kerusakan & Tindakan Perbaikan Terakhir -->
                                            <td>
                                                <?php if (!empty($a['keluhan_rusak_terakhir'])): ?>
                                                    <div class="mb-1">
                                                        <span class="badge bg-secondary" style="font-size: 10px;">Insiden Terakhir</span>
                                                        <span class="small fw-semibold text-danger">"<?= htmlspecialchars(substr($a['keluhan_rusak_terakhir'], 0, 50)) ?>"</span>
                                                    </div>
                                                    <div class="small text-dark">
                                                        <strong class="text-primary"><i class="bi bi-tools me-1"></i>Tindakan:</strong> <?= htmlspecialchars($a['tindakan_perbaikan_terakhir'] ?? 'Perbaikan teknis & kalibrasi') ?>
                                                    </div>
                                                    <?php if (!empty($a['sparepart_terakhir'])): ?>
                                                        <div class="small text-muted fst-italic" style="font-size: 11px;">
                                                            <i class="bi bi-cpu me-1"></i>Part: <?= htmlspecialchars($a['sparepart_terakhir']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-success small"><i class="bi bi-check-circle-fill me-1"></i>Belum ada riwayat kerusakan berat tercatat.</span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Pihak Bertanggung Jawab -->
                                            <td>
                                                <div class="small fw-semibold text-dark">
                                                    <i class="bi bi-person-fill-gear text-primary me-1"></i>Teknisi:
                                                    <span><?= htmlspecialchars($a['teknisi_pemeliharaan'] ?? $a['teknisi_perbaikan'] ?? 'Budi Setiawan (Elektromedis)') ?></span>
                                                </div>
                                                <div class="small text-muted mt-1">
                                                    <i class="bi bi-patch-check text-success me-1"></i>Kalibrasi:
                                                    <span><?= htmlspecialchars($a['petugas_kalibrasi'] ?? 'Ahmad Fauzi / BPFK') ?></span>
                                                </div>
                                            </td>

                                            <!-- Aksi -->
                                            <td class="text-center">
                                                <div class="d-flex flex-column gap-1">
                                                    <a href="logbook_aset.php?id=<?= $a['id_aset'] ?>" class="btn btn-outline-primary btn-sm py-1 px-2" style="font-size: 11px;" title="Buka Logbook & Riwayat Lengkap">
                                                        <i class="bi bi-journal-medical me-1"></i> Logbook
                                                    </a>
                                                    <button type="button" class="btn btn-light btn-sm text-dark border py-1 px-2 btn-checklist" 
                                                        style="font-size: 11px;"
                                                        data-id="<?= $a['id_aset'] ?>"
                                                        data-kode="<?= htmlspecialchars($a['kode_aset']) ?>"
                                                        data-nama="<?= htmlspecialchars($a['nama_aset']) ?>"
                                                        data-lokasi="<?= htmlspecialchars($a['lokasi']) ?>"
                                                        data-kondisi="<?= htmlspecialchars($a['kondisi'] ?? 'Baik') ?>"
                                                        data-jadwal="<?= htmlspecialchars($a['jadwal_maintenance_berikutnya'] ?? '') ?>"
                                                        data-bs-toggle="modal" data-bs-target="#modalChecklist">
                                                        <i class="bi bi-clipboard-check text-success me-1"></i> Checklist
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- MODAL CHECKLIST PEMANTAUAN KONDISI CEPAT -->
    <div class="modal fade" id="modalChecklist" tabindex="-1" aria-labelledby="modalChecklistLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form method="POST" action="">
                    <div class="modal-header bg-light">
                        <h6 class="modal-title fw-bold text-dark" id="modalChecklistLabel">
                            <i class="bi bi-clipboard2-check-fill text-primary me-2"></i>Entri Checklist Pemantauan Fisik Berkala
                        </h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="id_aset" id="modal_id_aset">
                        
                        <div class="p-3 bg-light rounded-3 mb-3 border">
                            <div class="fw-bold text-dark fs-6" id="modal_nama_aset">-</div>
                            <div class="small text-muted">
                                Kode: <span class="badge bg-dark font-monospace text-info" id="modal_kode_aset">-</span> |
                                Lokasi: <span class="fw-semibold text-danger" id="modal_lokasi_aset">-</span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Kondisi Fisik Terkini <span class="text-danger">*</span></label>
                            <select name="kondisi" id="modal_kondisi" class="form-select" required>
                                <option value="Baik">Baik (Layak Digunakan / Siap Pakai)</option>
                                <option value="Rusak Ringan">Rusak Ringan (Butuh Servis Minor)</option>
                                <option value="Perlu Perawatan">Perlu Perawatan / Kalibrasi</option>
                                <option value="Rusak Berat">Rusak Berat (Tidak Dapat Digunakan)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Jadwal Maintenance / Kalibrasi Berikutnya</label>
                            <input type="date" name="jadwal_maintenance_berikutnya" id="modal_jadwal" class="form-control">
                            <small class="text-muted">Tentukan tanggal jatuh tempo inspeksi berkala berikutnya.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Catatan Hasil Pemeriksaan & Temuan Visual</label>
                            <textarea name="catatan_pemantauan" class="form-control" rows="3" placeholder="Contoh: Kabel power aman, display monitor jelas, sensor berfungsi baik, segel kalibrasi utuh."></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Petugas Pemeriksa / Penanggung Jawab</label>
                            <input type="text" name="pemeriksa" class="form-control" value="<?= htmlspecialchars($nama_pengguna) ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="simpan_checklist" class="btn btn-primary btn-sm px-4 fw-semibold">
                            <i class="bi bi-save me-1"></i> Simpan Hasil Pemantauan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.btn-checklist').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.getElementById('modal_id_aset').value = this.getAttribute('data-id');
                document.getElementById('modal_kode_aset').innerText = this.getAttribute('data-kode');
                document.getElementById('modal_nama_aset').innerText = this.getAttribute('data-nama');
                document.getElementById('modal_lokasi_aset').innerText = this.getAttribute('data-lokasi');
                document.getElementById('modal_kondisi').value = this.getAttribute('data-kondisi') || 'Baik';
                document.getElementById('modal_jadwal').value = this.getAttribute('data-jadwal') || '';
            });
        });
    </script>
</body>

</html>
