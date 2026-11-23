<?php
session_start();
if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../login.php");
    exit;
}

include(__DIR__ . '/../config/koneksi.php');

$nama_pengguna = $_SESSION['nama_pengguna'] ?? 'Petugas';
$role = $_SESSION['level'] ?? $_SESSION['role'] ?? 'user';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$kode = isset($_GET['kode']) ? mysqli_real_escape_string($koneksi, trim($_GET['kode'])) : '';

$aset = null;
if ($id > 0) {
    $qAset = mysqli_query($koneksi, "SELECT * FROM aset WHERE id_aset = $id LIMIT 1");
    $aset = mysqli_fetch_assoc($qAset);
} elseif (!empty($kode)) {
    $qAset = mysqli_query($koneksi, "SELECT * FROM aset WHERE kode_aset = '$kode' LIMIT 1");
    $aset = mysqli_fetch_assoc($qAset);
}

if (!$aset) {
    $qFallback = mysqli_query($koneksi, "SELECT * FROM aset ORDER BY id_aset ASC LIMIT 1");
    $aset = mysqli_fetch_assoc($qFallback);
    if (!$aset) {
        echo "<script>alert('Data aset belum tersedia!'); window.location='monitoring_pemeliharaan.php';</script>";
        exit;
    }
}

$id_aset = (int)$aset['id_aset'];
$namaAsetTrim = trim($aset['nama_aset']);
$namaAsetEsc = mysqli_real_escape_string($koneksi, $namaAsetTrim);

// Handle simpan checklist pemantauan dari halaman logbook
$alertMsg = '';
$alertType = '';
if (isset($_POST['simpan_checklist'])) {
    $kondisi_baru   = mysqli_real_escape_string($koneksi, $_POST['kondisi']);
    $catatan_check  = mysqli_real_escape_string($koneksi, trim($_POST['catatan_pemantauan']));
    $pemeriksa      = mysqli_real_escape_string($koneksi, trim($_POST['pemeriksa'] ?? $nama_pengguna));
    $tgl_next_maint = !empty($_POST['jadwal_maintenance_berikutnya']) ? mysqli_real_escape_string($koneksi, $_POST['jadwal_maintenance_berikutnya']) : null;

    $nextSql = $tgl_next_maint ? ", jadwal_maintenance_berikutnya = '$tgl_next_maint'" : "";

    $updAset = mysqli_query($koneksi, "UPDATE aset SET 
        kondisi = '$kondisi_baru'
        $nextSql
        WHERE id_aset = $id_aset");

    mysqli_query($koneksi, "INSERT INTO audit_fisik (id_aset, tanggal_audit, kondisi_fisik, keterangan, auditor) 
        VALUES ($id_aset, NOW(), '$kondisi_baru', '$catatan_check', '$pemeriksa')");

    if ($updAset) {
        $alertMsg = "Hasil checklist pemantauan fisik berhasil ditambahkan ke buku logbook aset!";
        $alertType = "success";
        $aset['kondisi'] = $kondisi_baru;
        if ($tgl_next_maint) $aset['jadwal_maintenance_berikutnya'] = $tgl_next_maint;
    } else {
        $alertMsg = "Gagal menyimpan pemantauan: " . mysqli_error($koneksi);
        $alertType = "danger";
    }
}

// 1. Ambil Riwayat Perawatan & Kalibrasi
$qPerawatan = mysqli_query($koneksi, "SELECT * FROM perawatan 
    WHERE TRIM(nama_aset) COLLATE utf8mb4_general_ci = '$namaAsetEsc' COLLATE utf8mb4_general_ci 
    ORDER BY tanggal DESC, id DESC");
$listPerawatan = [];
$totalBiayaRawat = 0;
while ($p = mysqli_fetch_assoc($qPerawatan)) {
    $totalBiayaRawat += (float)($p['biaya_pemeliharaan'] ?? 0);
    $listPerawatan[] = $p;
}

// 2. Ambil Riwayat Kerusakan & Tindakan Perbaikan
$qKerusakan = mysqli_query($koneksi, "SELECT * FROM kerusakan 
    WHERE TRIM(nama_aset) COLLATE utf8mb4_general_ci = '$namaAsetEsc' COLLATE utf8mb4_general_ci 
    ORDER BY tanggal DESC, id DESC");
$listKerusakan = [];
$totalBiayaRusak = 0;
while ($k = mysqli_fetch_assoc($qKerusakan)) {
    $totalBiayaRusak += (float)($k['biaya_perbaikan'] ?? 0);
    $listKerusakan[] = $k;
}

// 3. Ambil Riwayat Audit Fisik / Inspeksi Checklist
$qAudit = mysqli_query($koneksi, "SELECT * FROM audit_fisik WHERE id_aset = $id_aset ORDER BY tanggal_audit DESC, id_audit DESC");
$listAudit = [];
while ($au = mysqli_fetch_assoc($qAudit)) {
    $listAudit[] = $au;
}

// 4. Ambil Riwayat Mutasi Lokasi Ruangan
$qMutasi = mysqli_query($koneksi, "SELECT * FROM riwayat_lokasi WHERE id_aset = $id_aset ORDER BY tanggal_pindah DESC, id_riwayat DESC");
$listMutasi = [];
while ($mu = mysqli_fetch_assoc($qMutasi)) {
    $listMutasi[] = $mu;
}

// Perhitungan Umur
$tahunSekarang = (int)date('Y');
$tahunMasuk = (int)date('Y', strtotime($aset['tanggal_masuk']));
$umurPakai = max(0, $tahunSekarang - $tahunMasuk);
$sisaUmur = (int)$aset['umur_ekonomis'] - $umurPakai;
$totalBiayaAkumulatif = $totalBiayaRawat + $totalBiayaRusak;
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logbook Aset: <?= htmlspecialchars($aset['nama_aset']) ?> | SIMARIS RS</title>
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
        }

        .content {
            padding: 30px;
        }

        .asset-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            padding: 24px;
            margin-bottom: 24px;
        }

        .badge-code {
            font-family: 'Roboto Mono', monospace;
            font-size: 0.9rem;
            padding: 5px 10px;
            border-radius: 6px;
            background: #0f172a;
            color: #38bdf8;
            font-weight: 600;
        }

        .table-custom {
            background: #fff;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            font-size: 0.85rem;
        }

        .table-custom thead th {
            background: #f1f5f9;
            color: #334155;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            padding: 10px 14px;
            border-bottom: 2px solid #cbd5e1;
        }

        .table-custom tbody td {
            padding: 12px 14px;
            vertical-align: middle;
        }

        .nav-tabs .nav-link {
            font-weight: 600;
            color: #64748b;
            border: none;
            border-bottom: 3px solid transparent;
            padding: 10px 20px;
        }

        .nav-tabs .nav-link.active {
            color: #0d9488;
            border-color: #0d9488;
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
            <div class="dashboard-header">
                <div>
                    <h4 class="fw-bold m-0"><i class="bi bi-journal-medical me-2"></i> BUKU REKAM MEDIS & LOGBOOK PEMELIHARAAN ASET</h4>
                    <small style="opacity: 0.9;">Rekam Jejak Historis Pemeliharaan Preventif, Riwayat Kerusakan, Tindakan Perbaikan, dan Penanggung Jawab Alat</small>
                </div>
                <div class="d-flex gap-2">
                    <a href="cetak_kartu_pemeliharaan.php?id=<?= $id_aset ?>" target="_blank" class="btn btn-danger btn-sm shadow-sm">
                        <i class="bi bi-printer-fill me-1"></i> Cetak Kartu Riwayat PDF
                    </a>
                    <a href="monitoring_pemeliharaan.php" class="btn btn-light btn-sm fw-bold text-dark shadow-sm">
                        <i class="bi bi-arrow-left me-1"></i> Kembali ke Monitoring
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

                <!-- KARTU IDENTITAS DAN METRIK ASET -->
                <div class="asset-card">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge-code"><?= htmlspecialchars($aset['kode_aset']) ?></span>
                                <?php if (($aset['tingkat_prioritas'] ?? '') === 'Kritis'): ?>
                                    <span class="badge bg-danger shadow-sm"><i class="bi bi-exclamation-triangle-fill"></i> Kritis (Life-Support)</span>
                                <?php elseif (($aset['tingkat_prioritas'] ?? '') === 'Penting'): ?>
                                    <span class="badge bg-warning text-dark"><i class="bi bi-shield"></i> Penting (Diagnostik)</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><i class="bi bi-check-circle"></i> Rutin (Umum)</span>
                                <?php endif; ?>
                                <span class="badge bg-<?= $aset['kategori_aset'] == 'Medis' ? 'danger' : 'primary' ?>"><?= htmlspecialchars($aset['kategori_aset']) ?></span>
                            </div>
                            <h3 class="fw-bold text-dark mb-1"><?= htmlspecialchars($aset['nama_aset']) ?></h3>
                            <div class="text-muted small mb-3">
                                Merek / Tipe: <strong class="text-dark"><?= htmlspecialchars($aset['tipe_aset'] ?? '-') ?></strong> |
                                Jenis: <strong class="text-dark"><?= htmlspecialchars($aset['jenis']) ?></strong> |
                                Tanggal Masuk: <strong class="text-dark"><?= date('d F Y', strtotime($aset['tanggal_masuk'])) ?></strong>
                            </div>

                            <div class="row g-2 pt-2 border-top">
                                <div class="col-md-6">
                                    <div class="small text-muted"><i class="bi bi-geo-alt-fill text-danger me-1"></i>Lokasi Saat Ini:</div>
                                    <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($aset['lokasi']) ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="small text-muted"><i class="bi bi-person-badge text-primary me-1"></i>Penanggung Jawab Ruangan:</div>
                                    <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($aset['pj_ruangan'] ?? 'Kepala Ruangan') ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4 mt-3 mt-lg-0 border-start ps-lg-4 text-center text-lg-start">
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="small text-muted">Kondisi Fisik:</span>
                                    <span class="badge bg-<?= ((int)$aset['stok_rusak'] > 0) ? 'danger' : (((int)$aset['stok_perawatan'] > 0) ? 'warning text-dark' : 'success') ?>">
                                        <?= htmlspecialchars($aset['kondisi'] ?? 'Baik (Siap Pakai)') ?>
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="small text-muted">Jadwal Servis/Kalibrasi:</span>
                                    <strong class="text-primary"><?= !empty($aset['jadwal_maintenance_berikutnya']) ? date('d-m-Y', strtotime($aset['jadwal_maintenance_berikutnya'])) : '-' ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="small text-muted">Sisa Umur Ekonomis:</span>
                                    <strong class="<?= $sisaUmur <= 1 ? 'text-danger' : 'text-success' ?>"><?= $sisaUmur ?> Tahun</strong>
                                </div>
                                <div class="d-flex justify-content-between pt-2 border-top">
                                    <span class="small fw-semibold text-dark">Total Biaya Pemeliharaan:</span>
                                    <strong class="text-danger">Rp <?= number_format($totalBiayaAkumulatif, 0, ',', '.') ?></strong>
                                </div>

                                <button type="button" class="btn btn-outline-primary btn-sm w-100 mt-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalChecklist">
                                    <i class="bi bi-clipboard-plus me-1"></i> Input Pemantauan Rutin
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TABS LOGBOOK RIWAYAT ASET -->
                <ul class="nav nav-tabs mb-3" id="logbookTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" id="maint-tab" data-bs-toggle="tab" data-bs-target="#maint-pane" type="button" role="tab">
                            <i class="bi bi-tools me-1 text-primary"></i> 1. Jadwal & Pemeliharaan Preventif (<?= count($listPerawatan) ?>)
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="kerusakan-tab" data-bs-toggle="tab" data-bs-target="#kerusakan-pane" type="button" role="tab">
                            <i class="bi bi-exclamation-triangle me-1 text-danger"></i> 2. Riwayat Kerusakan & Tindakan Perbaikan (<?= count($listKerusakan) ?>)
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="audit-tab" data-bs-toggle="tab" data-bs-target="#audit-pane" type="button" role="tab">
                            <i class="bi bi-clipboard2-check me-1 text-success"></i> 3. Checklist Hasil Pemantauan Fisik (<?= count($listAudit) ?>)
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="mutasi-tab" data-bs-toggle="tab" data-bs-target="#mutasi-pane" type="button" role="tab">
                            <i class="bi bi-arrow-left-right me-1 text-secondary"></i> 4. Riwayat Mutasi Ruangan (<?= count($listMutasi) ?>)
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="logbookTabsContent">
                    
                    <!-- TAB 1: PEMELIHARAAN PREVENTIF & KALIBRASI -->
                    <div class="tab-pane fade show active" id="maint-pane" role="tabpanel">
                        <div class="table-custom">
                            <div class="p-3 bg-white border-bottom d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-dark"><i class="bi bi-calendar2-check text-primary me-2"></i>Rekam Jejak Pemeliharaan Preventif & Kalibrasi Berkala</span>
                                <span class="badge bg-light text-dark border">Total Biaya: Rp <?= number_format($totalBiayaRawat, 0, ',', '.') ?></span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 35px;">No</th>
                                            <th style="width: 100px;">Tanggal</th>
                                            <th style="width: 140px;">Jenis Maintenance</th>
                                            <th>Langkah / Tindakan Pemeliharaan</th>
                                            <th>Teknisi Elektromedis</th>
                                            <th>Petugas Kalibrasi</th>
                                            <th class="text-center">Jadwal Kalibrasi Berikutnya</th>
                                            <th class="text-end">Biaya Servis</th>
                                            <th class="text-center" style="width: 90px;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($listPerawatan)): ?>
                                            <tr><td colspan="9" class="text-center py-4 text-muted">Belum ada rekam jejak jadwal perawatan/kalibrasi untuk alat ini.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($listPerawatan as $idxP => $p): ?>
                                                <tr>
                                                    <td class="text-center"><?= $idxP + 1 ?></td>
                                                    <td class="fw-semibold text-dark"><?= date('d-m-Y', strtotime($p['tanggal'])) ?></td>
                                                    <td>
                                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                                                            <?= htmlspecialchars($p['jenis_pemeliharaan'] ?? 'Preventif Rutin') ?>
                                                        </span>
                                                    </td>
                                                    <td><?= htmlspecialchars($p['tindakan_pemeliharaan'] ?? 'Pembersihan filter, uji fungsi kelistrikan & kalibrasi') ?></td>
                                                    <td><i class="bi bi-person me-1 text-secondary"></i><?= htmlspecialchars($p['teknisi'] ?? '-') ?></td>
                                                    <td><i class="bi bi-patch-check me-1 text-success"></i><?= htmlspecialchars($p['petugas_kalibrasi'] ?? 'Internal RS') ?></td>
                                                    <td class="text-center fw-bold text-dark">
                                                        <?= !empty($p['tanggal_kalibrasi_berikutnya']) && $p['tanggal_kalibrasi_berikutnya'] != '0000-00-00' ? date('d-m-Y', strtotime($p['tanggal_kalibrasi_berikutnya'])) : '-' ?>
                                                    </td>
                                                    <td class="text-end fw-semibold text-dark">Rp <?= number_format($p['biaya_pemeliharaan'] ?? 0, 0, ',', '.') ?></td>
                                                    <td class="text-center">
                                                        <span class="badge bg-<?= ($p['status'] === 'Selesai') ? 'success' : 'warning text-dark' ?>">
                                                            <?= htmlspecialchars($p['status']) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: RIWAYAT KERUSAKAN & TINDAKAN PERBAIKAN -->
                    <div class="tab-pane fade" id="kerusakan-pane" role="tabpanel">
                        <div class="table-custom">
                            <div class="p-3 bg-white border-bottom d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="fw-bold text-dark"><i class="bi bi-wrench-adjustable-circle text-danger me-2"></i>Riwayat Kerusakan, Penanganan & Tindakan Perbaikan Teknisi</span>
                                    <div class="small text-muted">Mencatat keluhan gangguan fungsi, tindakan solusi teknisi, suku cadang diganti, dan pihak pelapor.</div>
                                </div>
                                <span class="badge bg-danger">Total Biaya Perbaikan: Rp <?= number_format($totalBiayaRusak, 0, ',', '.') ?></span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 35px;">No</th>
                                            <th style="width: 100px;">Tgl Lapor</th>
                                            <th>Pelapor & Keluhan Kerusakan</th>
                                            <th>Tindakan Perbaikan Teknisi</th>
                                            <th>Suku Cadang (Spare Part) Diganti</th>
                                            <th>Teknisi Penanggung Jawab</th>
                                            <th class="text-center">Tgl Selesai</th>
                                            <th class="text-end">Biaya Perbaikan</th>
                                            <th class="text-center" style="width: 110px;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($listKerusakan)): ?>
                                            <tr><td colspan="9" class="text-center py-4 text-muted">Aset ini belum pernah mengalami riwayat kerusakan berat.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($listKerusakan as $idxK => $k): ?>
                                                <tr>
                                                    <td class="text-center"><?= $idxK + 1 ?></td>
                                                    <td class="fw-semibold text-dark"><?= date('d-m-Y', strtotime($k['tanggal'])) ?></td>
                                                    <td>
                                                        <div class="fw-bold text-danger">"<?= htmlspecialchars($k['keterangan']) ?>"</div>
                                                        <small class="text-muted"><i class="bi bi-person me-1"></i>Pelapor: <?= htmlspecialchars($k['pelapor'] ?? 'Petugas Ruangan') ?></small>
                                                    </td>
                                                    <td>
                                                        <div class="fw-medium text-dark"><i class="bi bi-tools text-primary me-1"></i><?= htmlspecialchars($k['tindakan_perbaikan'] ?? 'Pemeriksaan kelistrikan dan perbaikan modul') ?></div>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($k['sparepart_diganti'])): ?>
                                                            <span class="badge bg-light text-dark border"><i class="bi bi-cpu me-1"></i><?= htmlspecialchars($k['sparepart_diganti']) ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><strong class="text-primary"><?= htmlspecialchars($k['teknisi'] ?? 'Budi Setiawan') ?></strong></td>
                                                    <td class="text-center">
                                                        <?= !empty($k['tanggal_selesai']) && $k['tanggal_selesai'] != '0000-00-00' ? date('d-m-Y', strtotime($k['tanggal_selesai'])) : '<span class="badge bg-secondary">Dalam Proses</span>' ?>
                                                    </td>
                                                    <td class="text-end fw-semibold text-danger">Rp <?= number_format($k['biaya_perbaikan'] ?? 0, 0, ',', '.') ?></td>
                                                    <td class="text-center">
                                                        <span class="badge bg-<?= ($k['status'] === 'Selesai Diperbaiki' || $k['status'] === 'Selesai') ? 'success' : 'danger' ?>">
                                                            <?= htmlspecialchars($k['status']) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3: CHECKLIST PEMANTAUAN FISIK -->
                    <div class="tab-pane fade" id="audit-pane" role="tabpanel">
                        <div class="table-custom">
                            <div class="p-3 bg-white border-bottom d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-dark"><i class="bi bi-clipboard2-check text-success me-2"></i>Rekam Jejak Hasil Pemantauan Fisik Berkala (Checklist Inspeksi)</span>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalChecklist">
                                    <i class="bi bi-plus-lg me-1"></i> Tambah Hasil Pemantauan
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 35px;">No</th>
                                            <th style="width: 140px;">Tanggal Pemeriksaan</th>
                                            <th style="width: 140px;">Kondisi Fisik</th>
                                            <th>Hasil Temuan & Catatan Visual</th>
                                            <th>Petugas Pemeriksa / Auditor</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($listAudit)): ?>
                                            <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada catatan inspeksi fisik khusus untuk alat ini.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($listAudit as $idxA => $au): ?>
                                                <tr>
                                                    <td class="text-center"><?= $idxA + 1 ?></td>
                                                    <td class="fw-semibold text-dark"><?= date('d-m-Y H:i', strtotime($au['tanggal_audit'])) ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= $au['kondisi_fisik'] == 'Baik' ? 'success' : 'warning text-dark' ?>">
                                                            <?= htmlspecialchars($au['kondisi_fisik']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= htmlspecialchars($au['keterangan']) ?></td>
                                                    <td><i class="bi bi-person-check-fill text-primary me-1"></i><?= htmlspecialchars($au['auditor']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 4: RIWAYAT MUTASI LOKASI -->
                    <div class="tab-pane fade" id="mutasi-pane" role="tabpanel">
                        <div class="table-custom">
                            <div class="p-3 bg-white border-bottom">
                                <span class="fw-bold text-dark"><i class="bi bi-arrow-left-right text-secondary me-2"></i>Riwayat Perpindahan Lokasi / Ruangan Aset</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 35px;">No</th>
                                            <th style="width: 140px;">Waktu Mutasi</th>
                                            <th>Lokasi Sebelumnya</th>
                                            <th>Lokasi Baru</th>
                                            <th>Keterangan Mutasi</th>
                                            <th>Penanggung Jawab</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($listMutasi)): ?>
                                            <tr><td colspan="6" class="text-center py-4 text-muted">Alat ini belum pernah dipindahkan dari ruangan awalnya.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($listMutasi as $idxM => $mu): ?>
                                                <tr>
                                                    <td class="text-center"><?= $idxM + 1 ?></td>
                                                    <td class="fw-semibold text-dark"><?= date('d-m-Y H:i', strtotime($mu['tanggal_pindah'])) ?></td>
                                                    <td class="text-muted"><?= htmlspecialchars($mu['lokasi_sebelumnya'] ?? 'Pengadaan Baru') ?></td>
                                                    <td class="fw-bold text-primary"><i class="bi bi-geo-alt-fill text-danger me-1"></i><?= htmlspecialchars($mu['lokasi_baru']) ?></td>
                                                    <td><?= htmlspecialchars($mu['keterangan']) ?></td>
                                                    <td><i class="bi bi-person me-1 text-secondary"></i><?= htmlspecialchars($mu['penanggung_jawab']) ?></td>
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
        </div>
    </div>

    <!-- MODAL CHECKLIST PEMANTAUAN KONDISI -->
    <div class="modal fade" id="modalChecklist" tabindex="-1" aria-labelledby="modalChecklistLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form method="POST" action="">
                    <div class="modal-header bg-light">
                        <h6 class="modal-title fw-bold text-dark" id="modalChecklistLabel">
                            <i class="bi bi-clipboard2-check-fill text-primary me-2"></i>Entri Checklist Pemantauan Fisik
                        </h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="p-3 bg-light rounded-3 mb-3 border">
                            <div class="fw-bold text-dark"><?= htmlspecialchars($aset['nama_aset']) ?></div>
                            <div class="small text-muted">Kode: <span class="badge bg-dark font-monospace text-info"><?= htmlspecialchars($aset['kode_aset']) ?></span></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Kondisi Fisik Terkini <span class="text-danger">*</span></label>
                            <select name="kondisi" class="form-select" required>
                                <option value="Baik" <?= ($aset['kondisi'] == 'Baik') ? 'selected' : '' ?>>Baik (Layak Digunakan / Siap Pakai)</option>
                                <option value="Rusak Ringan" <?= ($aset['kondisi'] == 'Rusak Ringan') ? 'selected' : '' ?>>Rusak Ringan (Butuh Servis Minor)</option>
                                <option value="Perlu Perawatan" <?= ($aset['kondisi'] == 'Perlu Perawatan') ? 'selected' : '' ?>>Perlu Perawatan / Kalibrasi</option>
                                <option value="Rusak Berat" <?= ($aset['kondisi'] == 'Rusak Berat') ? 'selected' : '' ?>>Rusak Berat (Tidak Dapat Digunakan)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Jadwal Maintenance / Kalibrasi Berikutnya</label>
                            <input type="date" name="jadwal_maintenance_berikutnya" class="form-control" value="<?= htmlspecialchars($aset['jadwal_maintenance_berikutnya'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Hasil Temuan & Catatan Visual</label>
                            <textarea name="catatan_pemantauan" class="form-control" rows="3" placeholder="Contoh: Kabel dalam kondisi baik, display normal, segel kalibrasi utuh." required></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Petugas Pemeriksa / Penanggung Jawab</label>
                            <input type="text" name="pemeriksa" class="form-control" value="<?= htmlspecialchars($nama_pengguna) ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="simpan_checklist" class="btn btn-primary btn-sm px-4 fw-semibold">
                            <i class="bi bi-save me-1"></i> Simpan Catatan Logbook
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
