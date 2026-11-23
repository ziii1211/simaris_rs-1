<?php
session_start();
if (!isset($_SESSION['id_pengguna']) || ($_SESSION['role'] !== 'admin' && ($_SESSION['level'] ?? '') !== 'keuangan')) {
    header("Location: ../login.php");
    exit;
}

include(__DIR__ . '/../config/koneksi.php');

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;

$search = isset($_GET['search']) ? mysqli_real_escape_string($koneksi, trim($_GET['search'])) : '';
$kategoriFilter = isset($_GET['kategori']) ? mysqli_real_escape_string($koneksi, $_GET['kategori']) : '';
$dari = isset($_GET['dari']) ? mysqli_real_escape_string($koneksi, $_GET['dari']) : '';
$sampai = isset($_GET['sampai']) ? mysqli_real_escape_string($koneksi, $_GET['sampai']) : '';

$where = ["harga > 0"];
if ($search != '') $where[] = "(nama_aset LIKE '%$search%' OR asal_usul LIKE '%$search%')";
if ($kategoriFilter != '') $where[] = "kategori_aset = '$kategoriFilter'";
if ($dari != '') $where[] = "tanggal_masuk >= '$dari'";
if ($sampai != '') $where[] = "tanggal_masuk <= '$sampai'";

$whereSQL = 'WHERE ' . implode(' AND ', $where);

// =========================================================
// MENGHITUNG STATISTIK TOTAL DENGAN RUMUS DEPRESIASI (DIKALIKAN TOTAL STOK)
// =========================================================
$statSumQ = mysqli_query($koneksi, "SELECT harga, total_stok, tanggal_masuk, umur_ekonomis FROM aset $whereSQL");
$total_jenis_aset = 0;
$total_unit_aset = 0;
$total_harga_awal = 0;
$total_nilai_saat_ini = 0;

$tahun_sekarang = date('Y');

while ($rowStat = mysqli_fetch_assoc($statSumQ)) {
    $total_jenis_aset++;
    $stok = (int)($rowStat['total_stok'] ?? 0);
    $total_unit_aset += $stok;

    $harga_awal = $rowStat['harga'] * $stok;
    $umur_eko = isset($rowStat['umur_ekonomis']) ? (int)$rowStat['umur_ekonomis'] : 0;
    $tahun_masuk = date('Y', strtotime($rowStat['tanggal_masuk']));

    $total_harga_awal += $harga_awal;

    $nilai_buku = $harga_awal;
    if ($umur_eko > 0) {
        $selisih_tahun = $tahun_sekarang - $tahun_masuk;
        if ($selisih_tahun < 0) $selisih_tahun = 0;
        if ($selisih_tahun > $umur_eko) $selisih_tahun = $umur_eko;

        $penyusutan_per_tahun = $harga_awal / $umur_eko;
        $akumulasi = $selisih_tahun * $penyusutan_per_tahun;
        $nilai_buku = $harga_awal - $akumulasi;
    }
    $total_nilai_saat_ini += $nilai_buku;
}

$countQ = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM aset $whereSQL");
$totalRow = mysqli_fetch_assoc($countQ)['total'];
$offset = ($page - 1) * $perPage;

$dataQ = mysqli_query($koneksi, "SELECT * FROM aset $whereSQL ORDER BY tanggal_masuk DESC LIMIT $offset, $perPage");
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Nilai & Depresiasi Aset | SIMARIS RS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto+Mono:wght@500;600;700&display=swap" rel="stylesheet">
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
            background: #fff;
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 18px 20px;
            height: 100%;
            transition: transform 0.2s;
        }

        .card-stat:hover {
            transform: translateY(-3px);
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
            letter-spacing: 0.5px;
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
        <!-- Sidebar Sesuai Role -->
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
            <!-- Header -->
            <div class="dashboard-header">
                <div>
                    <h4 class="fw-bold m-0"><i class="bi bi-cash-stack me-2"></i> LAPORAN NILAI & DEPRESIASI ASET</h4>
                    <div class="small text-white-50">Monitoring Valuasi Nilai Perolehan (Investasi) & Nilai Buku Aset RS Bhayangkara</div>
                </div>
                <div>
                    <a href="laporan_nilai_cetak.php?<?= http_build_query($_GET) ?>" class="btn btn-light btn-sm fw-bold px-3 text-danger rounded-pill shadow-sm" target="_blank">
                        <i class="bi bi-file-earmark-pdf-fill me-1"></i> Cetak PDF
                    </a>
                </div>
            </div>

            <div class="content">
                <!-- Statistik Card Ringkasan -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card-stat border-start border-primary border-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-secondary small fw-bold text-uppercase">Total Unit Terdata</div>
                                    <h3 class="fw-bold text-dark mb-0 mt-1"><?= number_format($total_unit_aset, 0, ',', '.') ?> <span class="fs-6 fw-normal text-muted">Unit</span></h3>
                                    <div class="small text-muted mt-1">Dari <?= $total_jenis_aset ?> jenis aset aktif</div>
                                </div>
                                <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle fs-3">
                                    <i class="bi bi-box-seam"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card-stat border-start border-warning border-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-secondary small fw-bold text-uppercase">Total Nilai Investasi (Awal)</div>
                                    <h3 class="fw-bold text-dark mb-0 mt-1">Rp <?= number_format($total_harga_awal, 0, ',', '.') ?></h3>
                                    <div class="small text-muted mt-1">Akumulasi harga beli seluruh aset</div>
                                </div>
                                <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-circle fs-3">
                                    <i class="bi bi-wallet2"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card-stat border-start border-success border-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-secondary small fw-bold text-uppercase">Total Nilai Buku (Saat Ini)</div>
                                    <h3 class="fw-bold text-success mb-0 mt-1">Rp <?= number_format($total_nilai_saat_ini, 0, ',', '.') ?></h3>
                                    <div class="small text-muted mt-1">Setelah dikurangi depresiasi berjalan</div>
                                </div>
                                <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle fs-3">
                                    <i class="bi bi-graph-up"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Filter -->
                <div class="card shadow-sm border-0 mb-4 rounded-3">
                    <div class="card-body p-3">
                        <form class="row g-2 align-items-center" method="GET">
                            <div class="col-md-3">
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nama aset / asal usul..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-2">
                                <select name="kategori" class="form-select form-select-sm">
                                    <option value="">Semua Kategori</option>
                                    <option value="Medis" <?= $kategoriFilter == 'Medis' ? 'selected' : '' ?>>Medis</option>
                                    <option value="Non-Medis" <?= $kategoriFilter == 'Non-Medis' ? 'selected' : '' ?>>Non-Medis</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="date" name="dari" class="form-control form-control-sm" title="Tanggal Masuk Dari" value="<?= htmlspecialchars($dari) ?>">
                            </div>
                            <div class="col-md-2">
                                <input type="date" name="sampai" class="form-control form-control-sm" title="Tanggal Masuk Sampai" value="<?= htmlspecialchars($sampai) ?>">
                            </div>
                            <div class="col-md-3 d-flex gap-2">
                                <button class="btn btn-primary btn-sm fw-bold px-3 flex-fill" type="submit">
                                    <i class="bi bi-search me-1"></i> Filter
                                </button>
                                <?php if (!empty($search) || !empty($kategoriFilter) || !empty($dari) || !empty($sampai)): ?>
                                    <a href="laporan_nilai.php" class="btn btn-outline-secondary btn-sm" title="Reset Filter">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabel Data Nilai Aset -->
                <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold m-0 text-dark">
                            <i class="bi bi-table me-2 text-primary"></i> Rincian Nilai & Penyusutan Aset (Harga × Stok)
                        </h6>
                        <span class="badge bg-secondary">Total: <?= $totalRow ?> Aset</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-center">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="align-middle" style="width: 50px;">No</th>
                                    <th rowspan="2" class="align-middle text-start">Nama Aset</th>
                                    <th rowspan="2" class="align-middle">Kategori</th>
                                    <th rowspan="2" class="align-middle">Tahun Masuk</th>
                                    <th rowspan="2" class="align-middle">Umur Eko</th>
                                    <th rowspan="2" class="align-middle">Stok</th>
                                    <th colspan="3" class="text-center bg-light">Data Keuangan & Depresiasi Total</th>
                                </tr>
                                <tr>
                                    <th class="text-end">Harga Beli (Total)</th>
                                    <th class="text-end">Susut / Tahun</th>
                                    <th class="text-end">Nilai Buku (Saat Ini)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = $offset + 1;
                                if (mysqli_num_rows($dataQ) == 0): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-5 text-muted">
                                            <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                                            Data aset dengan nilai tidak ditemukan pada kriteria filter ini.
                                        </td>
                                    </tr>
                                <?php else:
                                    while ($r = mysqli_fetch_assoc($dataQ)):
                                        $stok = (int)($r['total_stok'] ?? 0);
                                        $harga_total = $r['harga'] * $stok;

                                        $umur = isset($r['umur_ekonomis']) ? (int)$r['umur_ekonomis'] : 0;
                                        $tgl_masuk = $r['tanggal_masuk'];
                                        $thn_masuk = date('Y', strtotime($tgl_masuk));
                                        $kategori = isset($r['kategori_aset']) ? $r['kategori_aset'] : '-';

                                        $susut_per_tahun = 0;
                                        $nilai_sekarang = $harga_total;

                                        if ($umur > 0) {
                                            $susut_per_tahun = $harga_total / $umur;

                                            $pakai = $tahun_sekarang - $thn_masuk;
                                            if ($pakai < 0) $pakai = 0;
                                            if ($pakai > $umur) $pakai = $umur;

                                            $akumulasi = $pakai * $susut_per_tahun;
                                            $nilai_sekarang = $harga_total - $akumulasi;
                                        }

                                        $badge = ($kategori == 'Medis') ? '<span class="badge bg-danger">Medis</span>' : '<span class="badge bg-primary">Non-Medis</span>';
                                        if ($kategori == '-' || empty($kategori)) $badge = '<span class="badge bg-secondary">-</span>';
                                ?>
                                    <tr>
                                        <td class="text-secondary"><?= $no ?></td>
                                        <td class="fw-bold text-start text-dark"><?= htmlspecialchars($r['nama_aset']) ?></td>
                                        <td><?= $badge ?></td>
                                        <td><?= $thn_masuk ?></td>
                                        <td><?= $umur > 0 ? "$umur Thn" : "-" ?></td>
                                        <td class="fw-bold text-primary"><?= $stok ?></td>
                                        <td class="text-end fw-semibold">Rp <?= number_format($harga_total, 0, ',', '.') ?></td>
                                        <td class="text-end text-danger">- Rp <?= number_format($susut_per_tahun, 0, ',', '.') ?></td>
                                        <td class="text-end fw-bold text-success">Rp <?= number_format($nilai_sekarang, 0, ',', '.') ?></td>
                                    </tr>
                                <?php
                                        $no++;
                                    endwhile;
                                endif;
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <?php
                    $totalPages = ceil($totalRow / $perPage);
                    if ($totalPages > 1): ?>
                        <div class="card-footer bg-white py-3 border-top d-flex justify-content-between align-items-center">
                            <div class="small text-muted">
                                Menampilkan halaman <?= $page ?> dari <?= $totalPages ?> (Total <?= $totalRow ?> aset)
                            </div>
                            <ul class="pagination pagination-sm m-0">
                                <?php
                                $queryParams = $_GET;
                                for ($p = 1; $p <= $totalPages; $p++):
                                    $queryParams['page'] = $p;
                                    $url = 'laporan_nilai.php?' . http_build_query($queryParams);
                                    $active = $p == $page ? 'active' : '';
                                ?>
                                    <li class="page-item <?= $active ?>"><a class="page-link" href="<?= $url ?>"><?= $p ?></a></li>
                                <?php endfor; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>