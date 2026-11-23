<?php
session_start();
if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../login.php");
    exit;
}

include(__DIR__ . '/../config/koneksi.php');

$nama_pengguna = $_SESSION['nama_pengguna'] ?? 'Petugas';
$role = $_SESSION['level'] ?? $_SESSION['role'] ?? 'user';

// Filter parameter
$filter_ruangan = isset($_GET['ruangan']) ? mysqli_real_escape_string($koneksi, $_GET['ruangan']) : '';
$filter_kategori = isset($_GET['kategori']) ? mysqli_real_escape_string($koneksi, $_GET['kategori']) : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($koneksi, $_GET['search']) : '';

// 1. Ambil Rekapitulasi per Ruangan (Matriks Sebaran Ruangan)
$qRekap = mysqli_query($koneksi, "SELECT 
    lokasi,
    COUNT(*) as total_aset,
    SUM(total_stok) as sum_stok,
    SUM(stok_tersedia) as sum_tersedia,
    SUM(stok_rusak) as sum_rusak,
    SUM(stok_perawatan) as sum_perawatan,
    SUM(CASE WHEN kategori_aset = 'Medis' THEN 1 ELSE 0 END) as jml_medis,
    SUM(CASE WHEN kategori_aset = 'Non-Medis' THEN 1 ELSE 0 END) as jml_nonmedis,
    SUM(harga * total_stok) as total_nilai_ruangan
FROM aset 
WHERE lokasi IS NOT NULL AND lokasi != ''
GROUP BY lokasi 
ORDER BY total_aset DESC, lokasi ASC");

$ruanganList = [];
$totalAsetRS = 0;
$totalUnitRS = 0;
$totalBaikRS = 0;
$totalRusakRS = 0;
$totalPerawatanRS = 0;
$totalNilaiRS = 0;

while ($r = mysqli_fetch_assoc($qRekap)) {
    $ruanganList[] = $r;
    $totalAsetRS += (int)$r['total_aset'];
    $totalUnitRS += (int)$r['sum_stok'];
    $totalBaikRS += (int)$r['sum_tersedia'];
    $totalRusakRS += (int)$r['sum_rusak'];
    $totalPerawatanRS += (int)$r['sum_perawatan'];
    $totalNilaiRS += (float)$r['total_nilai_ruangan'];
}

// 2. Query Detail Aset per Ruangan
$whereDetail = [];
if (!empty($filter_ruangan)) {
    $whereDetail[] = "lokasi = '$filter_ruangan'";
}
if (!empty($filter_kategori)) {
    $whereDetail[] = "kategori_aset = '$filter_kategori'";
}
if (!empty($search)) {
    $whereDetail[] = "(nama_aset LIKE '%$search%' OR kode_aset LIKE '%$search%' OR lokasi LIKE '%$search%')";
}

$whereClause = count($whereDetail) > 0 ? "WHERE " . implode(" AND ", $whereDetail) : "";
$qAset = mysqli_query($koneksi, "SELECT * FROM aset $whereClause ORDER BY lokasi ASC, nama_aset ASC");

$asetDetailList = [];
while ($row = mysqli_fetch_assoc($qAset)) {
    if (empty($row['kode_aset'])) {
        $prefix = ($row['kategori_aset'] == 'Medis') ? 'RSB-MED' : 'RSB-NON';
        $row['kode_aset'] = sprintf("%s-%03d", $prefix, $row['id_aset']);
    }
    $asetDetailList[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sebaran Aset Ruangan & Pelacakan | SIMARIS RS</title>
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
        }

        .card-stat:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.08);
        }

        /* Room Matrix Card */
        .room-card {
            background: #fff;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            padding: 18px;
            transition: all 0.2s ease-in-out;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .room-card:hover {
            border-color: #0d9488;
            box-shadow: 0 6px 16px rgba(13, 148, 136, 0.12);
        }

        .room-card.active-filter {
            border-color: #0d9488;
            background: #f0fdfa;
        }

        .badge-code {
            font-family: 'Roboto Mono', monospace;
            font-size: 0.85rem;
            background: #0f172a;
            color: #38bdf8;
            padding: 3px 8px;
            border-radius: 4px;
        }

        .table-responsive {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }

        .table thead th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            font-size: 0.8rem;
            text-transform: uppercase;
            padding: 12px 14px;
            border-bottom: 2px solid #e2e8f0;
        }

        .table tbody td {
            padding: 12px 14px;
            font-size: 0.875rem;
            vertical-align: middle;
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
                    <h4 class="fw-bold m-0"><i class="bi bi-diagram-3"></i> SEBARAN ASET RUANGAN & PELACAKAN</h4>
                    <div class="small text-white-50">Distribusi Fasilitas, Alkes, dan Kartu Inventaris Ruangan (KIR) RS Bhayangkara</div>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <a href="scan_aset.php" class="btn btn-warning btn-sm fw-bold px-3 text-dark rounded-pill">
                        <i class="bi bi-qr-code-scan me-1"></i> Buka Scanner QR
                    </a>
                    <a href="cetak_kir.php?semua=1" target="_blank" class="btn btn-light btn-sm fw-bold px-3 rounded-pill">
                        <i class="bi bi-printer-fill me-1"></i> Cetak Rekap Semua KIR
                    </a>
                </div>
            </div>

            <div class="content">
                <!-- Stat Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card card-stat bg-white p-3 border-start border-primary border-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-secondary small fw-bold">TOTAL RUANGAN AKTIF</div>
                                    <h3 class="fw-bold text-dark mb-0"><?= count($ruanganList) ?></h3>
                                    <div class="small text-muted">Unit pelayanan terdata</div>
                                </div>
                                <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle fs-4">
                                    <i class="bi bi-hospital"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card card-stat bg-white p-3 border-start border-success border-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-secondary small fw-bold">TOTAL ASET FISIK</div>
                                    <h3 class="fw-bold text-dark mb-0"><?= $totalAsetRS ?> Jenis</h3>
                                    <div class="small text-muted"><?= $totalUnitRS ?> Total Unit Fisik</div>
                                </div>
                                <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle fs-4">
                                    <i class="bi bi-boxes"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card card-stat bg-white p-3 border-start border-info border-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-secondary small fw-bold">KONDISI OPERASIONAL</div>
                                    <h4 class="fw-bold text-success mb-0"><?= $totalBaikRS ?> <span class="fs-6 text-muted">Baik / Ada</span></h4>
                                    <div class="small text-danger fw-semibold">
                                        <?= $totalRusakRS ?> Rusak | <?= $totalPerawatanRS ?> Perawatan
                                    </div>
                                </div>
                                <div class="bg-info bg-opacity-10 text-info p-3 rounded-circle fs-4">
                                    <i class="bi bi-check2-circle"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card card-stat bg-white p-3 border-start border-warning border-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-secondary small fw-bold">TOTAL NILAI ASET RUANGAN</div>
                                    <h4 class="fw-bold text-dark mb-0" style="font-size: 1.15rem;">Rp <?= number_format($totalNilaiRS, 0, ',', '.') ?></h4>
                                    <div class="small text-muted">Perolehan investasi</div>
                                </div>
                                <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-circle fs-4">
                                    <i class="bi bi-cash-stack"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section: Matriks Ruangan & Unit Pelayanan -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark m-0">
                        <i class="bi bi-grid-fill text-primary me-2"></i> Matriks Ruangan & Unit Pelayanan (<?= count($ruanganList) ?> Ruangan)
                    </h5>
                    <?php if (!empty($filter_ruangan)): ?>
                        <a href="sebaran_aset.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                            <i class="bi bi-x-circle me-1"></i> Reset Filter Ruangan
                        </a>
                    <?php endif; ?>
                </div>

                <div class="row g-3 mb-4">
                    <?php foreach ($ruanganList as $ruang): ?>
                        <?php
                        $isActive = ($filter_ruangan === $ruang['lokasi']);
                        $isMedis = ($ruang['jml_medis'] > 0);
                        ?>
                        <div class="col-md-4 col-lg-3">
                            <div class="room-card <?= $isActive ? 'active-filter' : '' ?>">
                                <div>
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div class="badge <?= $isMedis ? 'bg-danger' : 'bg-primary' ?> bg-opacity-10 text-<?= $isMedis ? 'danger' : 'primary' ?> fw-bold px-2 py-1 rounded">
                                            <i class="bi <?= $isMedis ? 'bi-heart-pulse' : 'bi-building' ?> me-1"></i>
                                            <?= $ruang['jml_medis'] ?> Medis | <?= $ruang['jml_nonmedis'] ?> Non-Medis
                                        </div>
                                        <span class="badge bg-light text-dark border"><?= $ruang['total_aset'] ?> Item</span>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-1" title="<?= htmlspecialchars($ruang['lokasi']) ?>">
                                        <?= htmlspecialchars($ruang['lokasi']) ?>
                                    </h6>
                                    <div class="text-secondary small mb-3">
                                        Total: <strong><?= $ruang['sum_stok'] ?> Unit</strong> (Rp <?= number_format($ruang['total_nilai_ruangan'], 0, ',', '.') ?>)
                                    </div>
                                    <!-- Progress/Kondisi Pills -->
                                    <div class="d-flex gap-1 mb-3">
                                        <span class="badge bg-success bg-opacity-75" style="font-size: 0.75rem;" title="Baik / Tersedia">
                                            ✓ <?= $ruang['sum_tersedia'] ?>
                                        </span>
                                        <?php if ($ruang['sum_rusak'] > 0): ?>
                                            <span class="badge bg-danger" style="font-size: 0.75rem;" title="Rusak">
                                                ✕ <?= $ruang['sum_rusak'] ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($ruang['sum_perawatan'] > 0): ?>
                                            <span class="badge bg-warning text-dark" style="font-size: 0.75rem;" title="Perawatan">
                                                ⚙ <?= $ruang['sum_perawatan'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="d-flex gap-2 pt-2 border-top">
                                    <a href="?ruangan=<?= urlencode($ruang['lokasi']) ?>" class="btn <?= $isActive ? 'btn-primary' : 'btn-outline-primary' ?> btn-sm flex-fill fw-semibold" style="font-size: 0.8rem;">
                                        <i class="bi bi-filter"></i> <?= $isActive ? 'Sedang Dipilih' : 'Filter Aset' ?>
                                    </a>
                                    <a href="cetak_kir.php?ruangan=<?= urlencode($ruang['lokasi']) ?>" target="_blank" class="btn btn-outline-dark btn-sm" title="Cetak KIR (Kartu Inventaris Ruangan)">
                                        <i class="bi bi-printer"></i> KIR
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Section: Rincian Data Aset -->
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-table text-primary fs-5"></i>
                            <h6 class="fw-bold m-0 text-dark">
                                Daftar Inventaris Fisik
                                <?= !empty($filter_ruangan) ? 'di Ruangan: <span class="text-primary">' . htmlspecialchars($filter_ruangan) . '</span>' : '(Seluruh Ruangan)' ?>
                            </h6>
                            <span class="badge bg-secondary rounded-pill"><?= count($asetDetailList) ?> Data</span>
                        </div>

                        <!-- Filter Form -->
                        <form method="GET" class="d-flex gap-2 flex-wrap m-0">
                            <input type="hidden" name="ruangan" value="<?= htmlspecialchars($filter_ruangan) ?>">
                            <select name="kategori" class="form-select form-select-sm" style="width: 140px;" onchange="this.form.submit()">
                                <option value="">Semua Kategori</option>
                                <option value="Medis" <?= $filter_kategori === 'Medis' ? 'selected' : '' ?>>Medis</option>
                                <option value="Non-Medis" <?= $filter_kategori === 'Non-Medis' ? 'selected' : '' ?>>Non-Medis</option>
                            </select>

                            <div class="input-group input-group-sm" style="width: 220px;">
                                <input type="text" name="search" class="form-control" placeholder="Cari nama / kode..." value="<?= htmlspecialchars($search) ?>">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">No</th>
                                    <th>Kode Aset</th>
                                    <th>Nama Aset & Spesifikasi</th>
                                    <th>Kategori</th>
                                    <th>Lokasi Ruangan</th>
                                    <th class="text-center">Total Stok</th>
                                    <th>Kondisi Fisik</th>
                                    <th class="text-end">Harga Satuan</th>
                                    <th style="width: 130px;" class="text-center">Aksi Pelacakan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($asetDetailList)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-5 text-muted">
                                            <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                                            Tidak ada aset yang ditemukan pada kriteria ruangan/filter ini.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($asetDetailList as $i => $a): ?>
                                        <tr>
                                            <td class="text-secondary"><?= $i + 1 ?></td>
                                            <td>
                                                <span class="badge-code"><?= htmlspecialchars($a['kode_aset']) ?></span>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($a['nama_aset']) ?></div>
                                                <div class="text-secondary small"><?= htmlspecialchars($a['jenis']) ?> (<?= htmlspecialchars($a['tipe_aset']) ?>)</div>
                                            </td>
                                            <td>
                                                <span class="badge <?= $a['kategori_aset'] == 'Medis' ? 'bg-danger' : 'bg-primary' ?>">
                                                    <?= htmlspecialchars($a['kategori_aset']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark">
                                                    <i class="bi bi-geo-alt-fill text-danger me-1"></i><?= htmlspecialchars($a['lokasi']) ?>
                                                </div>
                                            </td>
                                            <td class="text-center fw-bold text-primary">
                                                <?= $a['total_stok'] ?> Unit
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column gap-1" style="font-size: 0.78rem;">
                                                    <span class="badge bg-success bg-opacity-75 text-start">Tersedia: <?= $a['stok_tersedia'] ?></span>
                                                    <?php if ($a['stok_rusak'] > 0): ?>
                                                        <span class="badge bg-danger text-start">Rusak: <?= $a['stok_rusak'] ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($a['stok_perawatan'] > 0): ?>
                                                        <span class="badge bg-warning text-dark text-start">Perawatan: <?= $a['stok_perawatan'] ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="text-end fw-semibold text-dark">
                                                Rp <?= number_format($a['harga'], 0, ',', '.') ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="detail_aset_qr.php?kode=<?= urlencode($a['kode_aset']) ?>" class="btn btn-outline-dark" title="Lihat Detail & Riwayat QR">
                                                        <i class="bi bi-qr-code"></i>
                                                    </a>
                                                    <a href="cetak_label_qr.php?id=<?= $a['id_aset'] ?>" target="_blank" class="btn btn-outline-primary" title="Cetak Label Stiker QR">
                                                        <i class="bi bi-printer"></i>
                                                    </a>
                                                    <a href="aset_edit.php?id=<?= $a['id_aset'] ?>" class="btn btn-outline-warning" title="Edit Aset">
                                                        <i class="bi bi-pencil"></i>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
