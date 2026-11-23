<?php
session_start();
if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../login.php");
    exit;
}

include(__DIR__ . '/../config/koneksi.php');

$thn_skrg = date('Y');
$periode_tahun = isset($_GET['tahun']) && !empty($_GET['tahun']) ? (int)$_GET['tahun'] : (int)$thn_skrg;
$kategoriFilter = isset($_GET['kategori']) ? mysqli_real_escape_string($koneksi, $_GET['kategori']) : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($koneksi, trim($_GET['search'])) : '';

$where = ["harga > 0"];
if ($kategoriFilter != '') {
    $where[] = "kategori_aset = '$kategoriFilter'";
}
if ($search != '') {
    $where[] = "(nama_aset LIKE '%$search%' OR lokasi LIKE '%$search%')";
}
$whereSQL = 'WHERE ' . implode(' AND ', $where);

// Hitung rekapitulasi statistik
$statQ = mysqli_query($koneksi, "SELECT harga, total_stok, tanggal_masuk, umur_ekonomis FROM aset $whereSQL");
$total_unit = 0;
$total_perolehan = 0;
$total_beban_periode = 0;
$total_akumulasi = 0;
$total_nilai_buku = 0;
$count_aktif = 0;
$count_habis = 0;

while ($st = mysqli_fetch_assoc($statQ)) {
    $stok = (int)($st['total_stok'] ?? 0);
    $total_unit += $stok;
    $h_awal = (float)$st['harga'] * $stok;
    $total_perolehan += $h_awal;

    $umur = (int)($st['umur_ekonomis'] ?? 0);
    $thn_masuk = (int)date('Y', strtotime($st['tanggal_masuk']));

    if ($periode_tahun < $thn_masuk) {
        // Belum dibeli
        continue;
    }

    if ($umur > 0) {
        $d_per_tahun = $h_awal / $umur;
        $thn_awal = max(0, min($umur, $periode_tahun - $thn_masuk));
        $beban = ($thn_awal < $umur) ? $d_per_tahun : 0;
        $thn_akhir = max(0, min($umur, $periode_tahun - $thn_masuk + 1));
        $akum = $thn_akhir * $d_per_tahun;
        $nb = max(0, $h_awal - $akum);

        $total_beban_periode += $beban;
        $total_akumulasi += $akum;
        $total_nilai_buku += $nb;

        if ($thn_akhir < $umur) {
            $count_aktif++;
        } else {
            $count_habis++;
        }
    } else {
        $total_nilai_buku += $h_awal;
        $count_aktif++;
    }
}

// Ambil data untuk tabel
$sqlData = "SELECT * FROM aset $whereSQL ORDER BY tanggal_masuk DESC, nama_aset ASC";
$dataQ = mysqli_query($koneksi, $sqlData);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penyusutan Nilai Aset per Periode | SIMARIS RS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f8fafc;
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
            background: linear-gradient(90deg, #2c7a7b, #1cc88a);
            color: #fff;
            padding: 22px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #fff;
        }

        .content {
            padding: 30px;
        }

        .card-header-theme {
            background: linear-gradient(90deg, #2c7a7b, #1cc88a);
            color: #fff;
            font-weight: 600;
        }

        .stat-box {
            background: #fff;
            padding: 16px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            border-left: 5px solid #2c7a7b;
            height: 100%;
        }

        .table thead th {
            vertical-align: middle;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table tbody td {
            font-size: 0.88rem;
            vertical-align: middle;
        }
    </style>
</head>

<body>
    <div id="wrapper">
        <?php 
        if (($_SESSION['level'] ?? '') === 'keuangan') {
            include(__DIR__ . '/sidebar_keuangan.php');
        } else {
            include(__DIR__ . '/../sidebar.php');
        }
        ?>

        <div id="page-content-wrapper">
            <div class="dashboard-header">
                <div>
                    <h3 class="mb-0 fw-bold"><i class="bi bi-graph-down-arrow me-2"></i>Laporan Penyusutan Nilai Aset</h3>
                    <small class="text-white-50">Perhitungan Beban Penyusutan & Nilai Buku Periodik (Metode Garis Lurus)</small>
                </div>
                <div class="text-end">
                    <span class="badge bg-light text-dark px-3 py-2 fs-6">
                        <i class="bi bi-calendar-check me-1 text-success"></i> Periode Tahun: <?= $periode_tahun ?>
                    </span>
                </div>
            </div>

            <div class="content">
                <!-- FILTER FORM -->
                <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px;">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label fw-semibold text-muted small">Periode Tahun Buku</label>
                                <select name="tahun" class="form-select">
                                    <?php for ($thn = (int)$thn_skrg + 2; $thn >= 2020; $thn--): ?>
                                        <option value="<?= $thn ?>" <?= $periode_tahun == $thn ? 'selected' : '' ?>>Tahun <?= $thn ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold text-muted small">Kategori Aset</label>
                                <select name="kategori" class="form-select">
                                    <option value="">Semua Kategori</option>
                                    <option value="Medis" <?= $kategoriFilter == 'Medis' ? 'selected' : '' ?>>Medis</option>
                                    <option value="Non-Medis" <?= $kategoriFilter == 'Non-Medis' ? 'selected' : '' ?>>Non-Medis</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold text-muted small">Cari Nama Aset / Lokasi</label>
                                <input type="text" name="search" class="form-control" placeholder="Ketik kata kunci..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-3 d-flex gap-2">
                                <button type="submit" class="btn btn-success flex-fill">
                                    <i class="bi bi-filter"></i> Filter
                                </button>
                                <a href="laporan_penyusutan_periode.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- STATISTIK KEUANGAN PENYUSUTAN -->
                <div class="row g-3 mb-4">
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-box border-primary">
                            <small class="text-muted d-block text-uppercase fw-semibold">Total Nilai Perolehan</small>
                            <h4 class="fw-bold text-dark mt-1 mb-0">Rp <?= number_format($total_perolehan, 0, ',', '.') ?></h4>
                            <small class="text-muted"><?= $total_unit ?> Unit Aset Terdaftar</small>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-box border-warning">
                            <small class="text-muted d-block text-uppercase fw-semibold">Beban Susut Tahun <?= $periode_tahun ?></small>
                            <h4 class="fw-bold text-warning mt-1 mb-0">Rp <?= number_format($total_beban_periode, 0, ',', '.') ?></h4>
                            <small class="text-muted">Penyusutan periode berjalan</small>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-box border-danger">
                            <small class="text-muted d-block text-uppercase fw-semibold">Akumulasi Penyusutan</small>
                            <h4 class="fw-bold text-danger mt-1 mb-0">Rp <?= number_format($total_akumulasi, 0, ',', '.') ?></h4>
                            <small class="text-muted">Total susut s/d tahun <?= $periode_tahun ?></small>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-box border-success">
                            <small class="text-muted d-block text-uppercase fw-semibold">Nilai Buku (Tahun <?= $periode_tahun ?>)</small>
                            <h4 class="fw-bold text-success mt-1 mb-0">Rp <?= number_format($total_nilai_buku, 0, ',', '.') ?></h4>
                            <small class="text-muted"><?= $count_aktif ?> Aktif / <?= $count_habis ?> Habis Manfaat</small>
                        </div>
                    </div>
                </div>

                <!-- TABEL DATA -->
                <div class="card shadow-sm border-0" style="border-radius: 12px; overflow: hidden;">
                    <div class="card-header card-header-theme d-flex justify-content-between align-items-center py-3">
                        <span class="fs-6 fw-bold">
                            <i class="bi bi-table me-2"></i>Rincian Penyusutan Nilai Aset - Periode Tahun <?= $periode_tahun ?>
                        </span>
                        <div class="d-flex gap-2">
                            <a href="cetak_laporan_penyusutan.php?<?= http_build_query($_GET) ?>" target="_blank" class="btn btn-danger btn-sm px-3 fw-semibold">
                                <i class="bi bi-printer-fill me-1"></i> Cetak PDF Resmi
                            </a>
                            <a href="export_penyusutan_excel.php?<?= http_build_query($_GET) ?>" class="btn btn-light btn-sm px-3 fw-semibold text-success">
                                <i class="bi bi-file-earmark-excel-fill me-1"></i> Export Excel
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-bordered table-hover mb-0 text-center align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th rowspan="2" style="width: 40px;">No</th>
                                    <th rowspan="2" class="text-start">Nama Aset & Kategori</th>
                                    <th rowspan="2">Lokasi</th>
                                    <th rowspan="2">Tgl Masuk</th>
                                    <th rowspan="2">Umur (Thn)</th>
                                    <th rowspan="2">Qty</th>
                                    <th colspan="4" class="bg-light border-bottom">Nilai Keuangan & Depresiasi (Periode <?= $periode_tahun ?>)</th>
                                    <th rowspan="2">Status Manfaat</th>
                                </tr>
                                <tr>
                                    <th>Nilai Perolehan</th>
                                    <th>Beban Susut/Thn</th>
                                    <th>Akumulasi Susut</th>
                                    <th>Nilai Buku Akhir</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = 1;
                                if (mysqli_num_rows($dataQ) == 0):
                                ?>
                                    <tr>
                                        <td colspan="11" class="py-4 text-muted">Data aset bernilai ekonomis tidak ditemukan untuk kriteria filter ini.</td>
                                    </tr>
                                <?php
                                endif;

                                while ($r = mysqli_fetch_assoc($dataQ)):
                                    $stok = (int)($r['total_stok'] ?? 0);
                                    $h_total = (float)$r['harga'] * $stok;
                                    $umur = (int)($r['umur_ekonomis'] ?? 0);
                                    $tgl_masuk = $r['tanggal_masuk'];
                                    $thn_masuk = (int)date('Y', strtotime($tgl_masuk));
                                    $kategori = $r['kategori_aset'] ?? '-';

                                    if ($periode_tahun < $thn_masuk) {
                                        $beban = 0;
                                        $akum = 0;
                                        $nb = 0;
                                        $status_badge = '<span class="badge bg-secondary">Belum Diperoleh</span>';
                                    } elseif ($umur > 0) {
                                        $d_tahun = $h_total / $umur;
                                        $thn_awal = max(0, min($umur, $periode_tahun - $thn_masuk));
                                        $beban = ($thn_awal < $umur) ? $d_tahun : 0;
                                        $thn_akhir = max(0, min($umur, $periode_tahun - $thn_masuk + 1));
                                        $akum = $thn_akhir * $d_tahun;
                                        $nb = max(0, $h_total - $akum);

                                        if ($thn_akhir < $umur) {
                                            $tahun_ke = $thn_awal + 1;
                                            $status_badge = "<span class='badge bg-success'>Aktif (Thn ke-$tahun_ke/$umur)</span>";
                                        } else {
                                            $status_badge = "<span class='badge bg-danger'>Habis Manfaat</span>";
                                        }
                                    } else {
                                        $beban = 0;
                                        $akum = 0;
                                        $nb = $h_total;
                                        $status_badge = '<span class="badge bg-info text-dark">Tanpa Susut</span>';
                                    }

                                    $kat_badge = ($kategori == 'Medis') ? '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Medis</span>' : '<span class="badge bg-primary-subtle text-primary border border-primary-subtle">Non-Medis</span>';
                                ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td class="text-start">
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($r['nama_aset']) ?></div>
                                            <small><?= $kat_badge ?></small>
                                        </td>
                                        <td class="text-start"><small><?= htmlspecialchars($r['lokasi'] ?? '-') ?></small></td>
                                        <td><small><?= date('d/m/Y', strtotime($tgl_masuk)) ?></small></td>
                                        <td><?= $umur > 0 ? $umur : '-' ?></td>
                                        <td class="fw-bold text-primary"><?= $stok ?></td>
                                        <td class="text-end fw-semibold">Rp <?= number_format($h_total, 0, ',', '.') ?></td>
                                        <td class="text-end text-warning fw-semibold">Rp <?= number_format($beban, 0, ',', '.') ?></td>
                                        <td class="text-end text-danger fw-semibold">Rp <?= number_format($akum, 0, ',', '.') ?></td>
                                        <td class="text-end text-success fw-bold">Rp <?= number_format($nb, 0, ',', '.') ?></td>
                                        <td><?= $status_badge ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td colspan="6" class="text-end py-3">TOTAL KESELURUHAN (PERIODE TAHUN <?= $periode_tahun ?>):</td>
                                    <td class="text-end text-dark">Rp <?= number_format($total_perolehan, 0, ',', '.') ?></td>
                                    <td class="text-end text-warning">Rp <?= number_format($total_beban_periode, 0, ',', '.') ?></td>
                                    <td class="text-end text-danger">Rp <?= number_format($total_akumulasi, 0, ',', '.') ?></td>
                                    <td class="text-end text-success">Rp <?= number_format($total_nilai_buku, 0, ',', '.') ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div class="mt-3">
                    <a href="laporan.php" class="btn btn-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i> Kembali ke Menu Laporan
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
