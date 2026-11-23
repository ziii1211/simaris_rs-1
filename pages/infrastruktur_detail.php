<?php
session_start();

if (!isset($_SESSION['level']) && isset($_SESSION['role'])) {
    $_SESSION['level'] = $_SESSION['role'];
}
if (!isset($_SESSION['level'])) {
    $_SESSION['level'] = 'user';
}
if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../login.php");
    exit;
}

include(__DIR__ . '/../config/koneksi.php');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$namaParam = isset($_GET['ruangan']) ? mysqli_real_escape_string($koneksi, trim($_GET['ruangan'])) : '';

if ($id > 0) {
    $qInfra = mysqli_query($koneksi, "SELECT * FROM infrastruktur WHERE id_infrastruktur = $id LIMIT 1");
    $infra = mysqli_fetch_assoc($qInfra);
} elseif (!empty($namaParam)) {
    $qInfra = mysqli_query($koneksi, "SELECT * FROM infrastruktur WHERE nama_infrastruktur LIKE '%$namaParam%' OR '$namaParam' LIKE CONCAT('%', nama_infrastruktur, '%') LIMIT 1");
    $infra = mysqli_fetch_assoc($qInfra);
} else {
    $qInfra = mysqli_query($koneksi, "SELECT * FROM infrastruktur ORDER BY id_infrastruktur ASC LIMIT 1");
    $infra = mysqli_fetch_assoc($qInfra);
}

if (!$infra) {
    echo "<script>alert('Data infrastruktur tidak ditemukan!'); window.location='infrastruktur.php';</script>";
    exit;
}

// Logic Pencocokan Aset di dalam Infrastruktur (Parent-Child)
$nama = $infra['nama_infrastruktur'];
$tokens = [];
if (stripos($nama, 'IGD') !== false) $tokens[] = "lokasi LIKE '%IGD%'";
if (stripos($nama, 'ICU') !== false) $tokens[] = "lokasi LIKE '%ICU%'";
if (stripos($nama, 'Operasi') !== false || stripos($nama, 'OK') !== false) $tokens[] = "lokasi LIKE '%Operasi%'";
if (stripos($nama, 'VVIP') !== false) $tokens[] = "lokasi LIKE '%VVIP%'";
if (stripos($nama, 'Kelas 1') !== false) $tokens[] = "lokasi LIKE '%Kelas 1%'";
if (stripos($nama, 'Kelas 2') !== false) $tokens[] = "lokasi LIKE '%Kelas 2%'";
if (stripos($nama, 'Kelas 3') !== false) $tokens[] = "lokasi LIKE '%Kelas 3%'";
if (stripos($nama, 'Gigi') !== false) $tokens[] = "lokasi LIKE '%Gigi%'";
if (stripos($nama, 'Farmasi') !== false) $tokens[] = "lokasi LIKE '%Farmasi%'";
if (stripos($nama, 'Radiologi') !== false) $tokens[] = "lokasi LIKE '%Radiologi%'";
if (stripos($nama, 'Server') !== false || stripos($nama, 'IT') !== false) $tokens[] = "lokasi LIKE '%Server%' OR lokasi LIKE '%IT%'";

if ($infra['kategori'] === 'Gedung') {
    if (stripos($nama, 'Utama') !== false) {
        $whereMatch = "(lokasi LIKE '%Administrasi%' OR lokasi LIKE '%Gudang Utama%' OR lokasi LIKE '%Poli%')";
    } else {
        $whereMatch = "(lokasi LIKE '%Rawat Inap%' OR lokasi LIKE '%ICU%' OR lokasi LIKE '%Operasi%')";
    }
} elseif (!empty($tokens)) {
    $whereMatch = "(" . implode(" OR ", $tokens) . " OR lokasi = '" . mysqli_real_escape_string($koneksi, $nama) . "')";
} else {
    $whereMatch = "lokasi LIKE '%" . mysqli_real_escape_string($koneksi, $nama) . "%'";
}

// Query Aset di dalam Ruangan
$sqlAset = "SELECT * FROM aset WHERE $whereMatch ORDER BY FIELD(tingkat_prioritas, 'Kritis', 'Penting', 'Rutin') ASC, nama_aset ASC";
$resAset = mysqli_query($koneksi, $sqlAset);

// Agregat Statistik Aset dalam Ruangan
$totalJenisAset = 0;
$totalUnitAset = 0;
$totalNilaiAset = 0;
$totalAsetBaik = 0;
$totalAsetRusak = 0;
$totalAsetPerawatan = 0;
$totalAsetKritis = 0;

$listAset = [];
while ($a = mysqli_fetch_assoc($resAset)) {
    $listAset[] = $a;
    $totalJenisAset++;
    $stok = $a['total_stok'] ?? 1;
    $totalUnitAset += $stok;
    $totalNilaiAset += ($a['harga'] * $stok);
    $totalAsetBaik += ($a['stok_tersedia'] ?? 0);
    $totalAsetRusak += ($a['stok_rusak'] ?? 0);
    $totalAsetPerawatan += ($a['stok_perawatan'] ?? 0);
    if (($a['tingkat_prioritas'] ?? '') === 'Kritis') {
        $totalAsetKritis++;
    }
}

// Parameter Badge
$katBadge = match ($infra['kategori']) {
    'Gedung' => 'bg-primary',
    'Ruangan Pelayanan' => 'bg-teal text-white',
    'Kamar Rawat Inap' => 'bg-purple text-white',
    default => 'bg-warning text-dark'
};
$kondisiBadge = match ($infra['kondisi']) {
    'Baik' => 'bg-success',
    'Rusak Ringan' => 'bg-warning text-dark',
    default => 'bg-danger'
};
$prioBadge = match ($infra['tingkat_prioritas'] ?? 'Penting') {
    'Kritis' => 'bg-danger',
    'Penting' => 'bg-warning text-dark',
    default => 'bg-secondary'
};
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Infrastruktur: <?= htmlspecialchars($infra['nama_infrastruktur']) ?> | SIMARIS RS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto+Mono:wght@500;700&display=swap" rel="stylesheet">
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
            border-bottom: 3px solid #14b8a6;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .content {
            padding: 25px 30px 40px 30px;
        }

        .card-custom {
            background: #fff;
            border: none;
            border-radius: 14px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
            margin-bottom: 24px;
            overflow: hidden;
        }

        .card-header-custom {
            background: linear-gradient(90deg, #1e3a8a, #0d9488);
            color: #fff;
            padding: 14px 20px;
            font-weight: 600;
            font-size: 1.05rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 18px 20px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
        }

        .stat-val {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
        }

        .table-custom thead th {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 0.82rem;
            font-weight: 600;
            text-transform: uppercase;
            border-bottom: 2px solid #cbd5e1;
            padding: 12px 10px;
            vertical-align: middle;
        }

        .table-custom tbody td {
            font-size: 0.85rem;
            padding: 12px 10px;
            vertical-align: middle;
        }

        .bg-teal { background-color: #0d9488 !important; }
        .bg-purple { background-color: #7c3aed !important; }

        .concept-banner {
            background: linear-gradient(135deg, #eff6ff, #f0fdf4);
            border-left: 5px solid #2563eb;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
        }

        .font-mono {
            font-family: 'Roboto Mono', monospace;
        }
    </style>
</head>

<body>
    <div id="wrapper">
        <!-- SIDEBAR -->
        <?php 
        if (($_SESSION['level'] ?? '') === 'keuangan') {
            include(__DIR__ . '/sidebar_keuangan.php');
        } else {
            include(__DIR__ . '/../sidebar.php');
        }
        ?>

        <!-- PAGE CONTENT -->
        <div id="page-content-wrapper">
            <!-- HEADER -->
            <div class="dashboard-header">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-light text-dark font-mono fs-6 px-2 py-1"><?= htmlspecialchars($infra['kode_infrastruktur']) ?></span>
                        <span class="badge <?= $prioBadge ?>"><?= htmlspecialchars($infra['tingkat_prioritas'] ?? 'Penting') ?></span>
                        <span class="badge <?= $katBadge ?>"><?= htmlspecialchars($infra['kategori']) ?></span>
                    </div>
                    <h3 class="fw-bold m-0"><?= htmlspecialchars($infra['nama_infrastruktur']) ?></h3>
                    <small style="opacity: 0.9;"><i class="bi bi-geo-alt-fill me-1"></i> Gedung: <?= htmlspecialchars($infra['nama_gedung']) ?> | Lantai: <?= htmlspecialchars($infra['lantai']) ?></small>
                </div>
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <button type="button" class="btn btn-outline-light btn-sm fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalMatriksPerbedaan">
                        <i class="bi bi-diagram-3-fill me-1"></i> Perbedaan Konseptual
                    </button>
                    <a href="cetak_kir.php?ruangan=<?= urlencode($infra['nama_infrastruktur']) ?>" target="_blank" class="btn btn-warning btn-sm fw-bold text-dark shadow-sm">
                        <i class="bi bi-printer me-1"></i> Cetak KIR Ruangan PDF
                    </a>
                    <a href="infrastruktur.php" class="btn btn-light btn-sm text-dark fw-semibold shadow-sm">
                        <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
                    </a>
                </div>
            </div>

            <!-- CONTENT -->
            <div class="content">

                <!-- BANNER PENJELASAN KONSEPTUAL: WADAH VS ISI -->
                <div class="concept-banner shadow-sm">
                    <div class="d-flex align-items-start gap-3">
                        <div class="fs-2 text-primary"><i class="bi bi-building-check"></i></div>
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <h5 class="fw-bold text-dark m-0">Hubungan Relasional: Infrastruktur (Wadah) vs Aset (Isi)</h5>
                                <span class="badge bg-primary">Standar STARKES MFK</span>
                            </div>
                            <p class="text-secondary small mb-2">
                                Dalam arsitektur sistem SIMARIS RS, <strong><?= htmlspecialchars($infra['nama_infrastruktur']) ?></strong> berfungsi sebagai entitas <strong>Infrastruktur Fisik Tetap (Prasarana)</strong> yang menyediakan ruangan, kapasitas, dan lingkungan kerja pelayanan. Di bawah ini tercatat seluruh <strong>Aset & Peralatan Medis/Sarpras (Barang Bergerak)</strong> yang ditempatkan, digunakan, serta beroperasi di dalam ruangan ini.
                            </p>
                            <div class="d-flex gap-3 flex-wrap small text-muted">
                                <span><i class="bi bi-person-badge text-primary me-1"></i> Penanggung Jawab Ruangan: <strong><?= htmlspecialchars($infra['penanggung_jawab'] ?? 'Koordinator Ruangan') ?></strong></span>
                                <span><i class="bi bi-door-open text-success me-1"></i> Kapasitas Bed: <strong><?= htmlspecialchars($infra['kapasitas'] ?: '-') ?></strong></span>
                                <span><i class="bi bi-aspect-ratio text-info me-1"></i> Luas Area: <strong><?= htmlspecialchars($infra['luas_area'] ?: '-') ?></strong></span>
                                <span><i class="bi bi-shield-check text-warning me-1"></i> Kelaikan Bangunan: <strong><?= htmlspecialchars($infra['kondisi']) ?></strong></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4 KARTU STATISTIK ASET DI RUANGAN INI -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card">
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-boxes"></i>
                            </div>
                            <div>
                                <div class="stat-val"><?= $totalJenisAset ?> <span style="font-size: 0.9rem; font-weight: normal; color: #64748b;">Jenis</span></div>
                                <div class="stat-label">Total Jenis Aset</div>
                                <small class="text-muted"><?= $totalUnitAset ?> Total Unit Fisik</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card">
                            <div class="stat-icon bg-success bg-opacity-10 text-success">
                                <i class="bi bi-cash-coin"></i>
                            </div>
                            <div>
                                <div class="stat-val" style="font-size: 1.2rem;">Rp <?= number_format($totalNilaiAset, 0, ',', '.') ?></div>
                                <div class="stat-label">Kapitalisasi Aset Ruang</div>
                                <small class="text-muted">Total Nilai Perolehan</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card">
                            <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                                <i class="bi bi-shield-fill-exclamation"></i>
                            </div>
                            <div>
                                <div class="stat-val"><?= $totalAsetKritis ?> <span style="font-size: 0.9rem; font-weight: normal; color: #64748b;">Alat</span></div>
                                <div class="stat-label">Aset Kritis Pelayanan</div>
                                <small class="text-muted">Prioritas Life-Support</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card">
                            <div class="stat-icon bg-info bg-opacity-10 text-info">
                                <i class="bi bi-heart-pulse"></i>
                            </div>
                            <div>
                                <div class="stat-val"><?= $totalUnitAset > 0 ? round(($totalAsetBaik / $totalUnitAset) * 100, 1) : 100 ?>%</div>
                                <div class="stat-label">Kesiapan Aset Ruangan</div>
                                <small class="text-success"><?= $totalAsetBaik ?> Baik / <?= $totalAsetRusak ?> Rusak</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TABEL ASET YANG BERADA DI DALAM RUANGAN INI -->
                <div class="card card-custom">
                    <div class="card-header-custom">
                        <div>
                            <i class="bi bi-box-seam me-2"></i> Daftar Aset & Peralatan di Dalam <?= htmlspecialchars($infra['nama_infrastruktur']) ?>
                        </div>
                        <div>
                            <span class="badge bg-light text-dark"><?= count($listAset) ?> Aset Terdaftar</span>
                        </div>
                    </div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-hover table-bordered table-custom mb-0 align-middle">
                            <thead>
                                <tr class="text-center">
                                    <th width="40">No</th>
                                    <th width="120">Kode Aset</th>
                                    <th>Nama Aset & Spesifikasi</th>
                                    <th width="110">Kategori</th>
                                    <th width="110">Prioritas</th>
                                    <th>Stok Ruangan</th>
                                    <th>Kondisi Fisik</th>
                                    <th>Harga Perolehan</th>
                                    <th>Jadwal Maintenance</th>
                                    <th>PJ Medis / Teknis</th>
                                    <th width="110">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($listAset)): ?>
                                    <tr>
                                        <td colspan="11" class="text-center py-5 text-muted">
                                            <i class="bi bi-box fs-1 d-block mb-2 text-secondary"></i>
                                            Belum ada aset/peralatan yang dialokasikan ke ruangan ini.
                                            <div class="mt-2">
                                                <a href="aset_pindah.php" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-arrow-left-right me-1"></i> Mutasi Aset ke Ruangan Ini
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php 
                                    $no = 1;
                                    foreach ($listAset as $item): 
                                        $idAset = (int)$item['id_aset'];
                                        $kodeAset = !empty($item['kode_aset']) ? $item['kode_aset'] : sprintf("RSB-%03d", $idAset);
                                        $kondisiA = $item['kondisi'] ?? 'Baik';
                                        $prioA = $item['tingkat_prioritas'] ?? 'Penting';
                                        $isKritis = ($prioA === 'Kritis');

                                        $badgeKondisiA = ($kondisiA == 'Baik') ? 'bg-success' : (($kondisiA == 'Rusak Berat') ? 'bg-danger' : 'bg-warning text-dark');
                                        $badgePrioA = ($prioA == 'Kritis') ? 'bg-danger' : (($prioA == 'Penting') ? 'bg-warning text-dark' : 'bg-secondary');
                                    ?>
                                        <tr style="<?= $isKritis ? 'background-color: #fff9f9;' : ''; ?>">
                                            <td class="text-center"><?= $no++ ?></td>
                                            <td class="text-center font-mono fw-bold text-primary"><?= htmlspecialchars($kodeAset) ?></td>
                                            <td>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($item['nama_aset']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($item['jenis'] ?? '-') ?> | Tipe: <?= htmlspecialchars($item['tipe_aset'] ?? '-') ?></small>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge <?= ($item['kategori_aset'] == 'Medis') ? 'bg-danger' : 'bg-primary' ?>">
                                                    <?= htmlspecialchars($item['kategori_aset']) ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge <?= $badgePrioA ?>">
                                                    <?= htmlspecialchars($prioA) ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-light text-dark border px-2 py-1 fs-6">
                                                    <?= $item['total_stok'] ?? 1 ?> Unit
                                                </span>
                                                <div class="small mt-1">
                                                    <span class="text-success fw-bold"><?= $item['stok_tersedia'] ?? 0 ?> Baik</span> | 
                                                    <span class="text-danger fw-bold"><?= $item['stok_rusak'] ?? 0 ?> Rsk</span>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge <?= $badgeKondisiA ?> px-2 py-1"><?= htmlspecialchars($kondisiA) ?></span>
                                            </td>
                                            <td class="text-end fw-semibold">
                                                Rp <?= number_format($item['harga'], 0, ',', '.') ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if (!empty($item['jadwal_maintenance_berikutnya'])): 
                                                    $tglM = $item['jadwal_maintenance_berikutnya'];
                                                    $diffD = round((strtotime($tglM) - time()) / 86400);
                                                ?>
                                                    <span class="badge <?= $diffD < 0 ? 'bg-danger' : ($diffD <= 14 ? 'bg-warning text-dark' : 'bg-light text-dark border') ?>">
                                                        <?= date('d/m/Y', strtotime($tglM)) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted small">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="small fw-semibold text-dark"><?= htmlspecialchars($item['pj_ruangan'] ?: $infra['penanggung_jawab']) ?></div>
                                                <small class="text-muted">Teknisi: Ahmad Fauzi</small>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="detail_aset_qr.php?id=<?= $idAset ?>" class="btn btn-outline-primary" title="Scan / Detail QR">
                                                        <i class="bi bi-qr-code"></i>
                                                    </a>
                                                    <a href="logbook_aset.php?id=<?= $idAset ?>" class="btn btn-outline-success" title="Buku Riwayat Aset">
                                                        <i class="bi bi-journal-medical"></i>
                                                    </a>
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

    <!-- INCLUDE MODAL MATRIKS PERBEDAAN ASET VS INFRASTRUKTUR -->
    <?php include(__DIR__ . '/modal_perbedaan_aset_infrastruktur.php'); ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
