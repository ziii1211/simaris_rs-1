<?php
session_start();
if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../login.php");
    exit;
}

include(__DIR__ . '/../config/koneksi.php');

$search = isset($_GET['search']) ? mysqli_real_escape_string($koneksi, trim($_GET['search'])) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($koneksi, $_GET['status']) : '';
$periode_filter = isset($_GET['periode']) ? mysqli_real_escape_string($koneksi, $_GET['periode']) : '';

$where = [];
if ($search != '') {
    $where[] = "(so.kode_opname LIKE '%$search%' OR a.nama_aset LIKE '%$search%' OR so.lokasi_opname LIKE '%$search%' OR so.petugas_opname LIKE '%$search%')";
}
if ($status_filter != '') {
    $where[] = "so.status_selisih = '$status_filter'";
}
if ($periode_filter != '') {
    $where[] = "so.periode_opname = '$periode_filter'";
}

$whereSQL = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Statistik
$statQ = mysqli_query($koneksi, "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status_selisih = 'Sesuai' THEN 1 ELSE 0 END) as total_sesuai,
        SUM(CASE WHEN status_selisih = 'Selisih Kurang' THEN 1 ELSE 0 END) as total_kurang,
        SUM(CASE WHEN status_selisih = 'Selisih Lebih' THEN 1 ELSE 0 END) as total_lebih
    FROM stock_opname so
");
$stat = mysqli_fetch_assoc($statQ);

// Query Data Opname
$sql = "
    SELECT so.*, a.nama_aset, a.kategori_aset
    FROM stock_opname so
    JOIN aset a ON so.id_aset = a.id_aset
    $whereSQL
    ORDER BY so.tanggal_opname DESC, so.id_opname DESC
";
$res = mysqli_query($koneksi, $sql);

// Daftar unik periode untuk filter dropdown
$qPeriode = mysqli_query($koneksi, "SELECT DISTINCT periode_opname FROM stock_opname ORDER BY periode_opname DESC");
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Opname Aset (Fisik vs Sistem) | SIMARIS RS</title>
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

        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 18px 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            border-left: 5px solid #2c7a7b;
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
        <?php include(__DIR__ . '/../sidebar.php'); ?>

        <div id="page-content-wrapper">
            <div class="dashboard-header">
                <div>
                    <h3 class="mb-0 fw-bold"><i class="bi bi-clipboard2-check me-2"></i>Hasil Stock Opname Aset</h3>
                    <small class="text-white-50">Monitoring Komparasi Kuantitas Fisik Lapangan vs Saldo Sistem</small>
                </div>
                <div>
                    <a href="stock_opname_tambah.php" class="btn btn-light fw-bold text-success shadow-sm">
                        <i class="bi bi-plus-circle-fill me-1"></i> Input Stock Opname Baru
                    </a>
                </div>
            </div>

            <div class="content">
                <!-- KARTU STATISTIK -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="stat-card border-primary">
                            <small class="text-muted d-block text-uppercase fw-semibold">Total Item Diperiksa</small>
                            <h3 class="fw-bold text-dark mt-1 mb-0"><?= (int)$stat['total'] ?></h3>
                            <small class="text-muted">Aset telah di-opname fisik</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card border-success">
                            <small class="text-muted d-block text-uppercase fw-semibold">Sesuai / Klop (Selisih 0)</small>
                            <h3 class="fw-bold text-success mt-1 mb-0"><?= (int)$stat['total_sesuai'] ?></h3>
                            <small class="text-muted">Fisik cocok dengan data sistem</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card border-danger">
                            <small class="text-muted d-block text-uppercase fw-semibold">Selisih Kurang (Minus)</small>
                            <h3 class="fw-bold text-danger mt-1 mb-0"><?= (int)$stat['total_kurang'] ?></h3>
                            <small class="text-muted">Fisik lebih sedikit dari sistem</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card border-warning">
                            <small class="text-muted d-block text-uppercase fw-semibold">Selisih Lebih (Plus)</small>
                            <h3 class="fw-bold text-warning mt-1 mb-0"><?= (int)$stat['total_lebih'] ?></h3>
                            <small class="text-muted">Fisik lebih banyak dari sistem</small>
                        </div>
                    </div>
                </div>

                <!-- FORM FILTER -->
                <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px;">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-muted small">Cari Aset / Kode / Pemeriksa</label>
                                <input type="text" name="search" class="form-control" placeholder="Ketik pencarian..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold text-muted small">Status Selisih</label>
                                <select name="status" class="form-select">
                                    <option value="">Semua Status</option>
                                    <option value="Sesuai" <?= $status_filter == 'Sesuai' ? 'selected' : '' ?>>Sesuai (Klop)</option>
                                    <option value="Selisih Kurang" <?= $status_filter == 'Selisih Kurang' ? 'selected' : '' ?>>Selisih Kurang</option>
                                    <option value="Selisih Lebih" <?= $status_filter == 'Selisih Lebih' ? 'selected' : '' ?>>Selisih Lebih</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold text-muted small">Periode Opname</label>
                                <select name="periode" class="form-select">
                                    <option value="">Semua Periode</option>
                                    <?php while ($p = mysqli_fetch_assoc($qPeriode)): ?>
                                        <option value="<?= htmlspecialchars($p['periode_opname']) ?>" <?= $periode_filter == $p['periode_opname'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['periode_opname']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex gap-2">
                                <button type="submit" class="btn btn-success flex-fill">
                                    <i class="bi bi-filter"></i> Filter
                                </button>
                                <a href="stock_opname.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- TABEL DATA -->
                <div class="card shadow-sm border-0" style="border-radius: 12px; overflow: hidden;">
                    <div class="card-header card-header-theme d-flex justify-content-between align-items-center py-3">
                        <span class="fs-6 fw-bold">
                            <i class="bi bi-table me-2"></i>Daftar Riwayat Perhitungan Stock Opname
                        </span>
                        <div class="d-flex gap-2">
                            <a href="cetak_laporan_stock_opname.php?<?= http_build_query($_GET) ?>" target="_blank" class="btn btn-danger btn-sm px-3 fw-semibold">
                                <i class="bi bi-printer-fill me-1"></i> Cetak PDF
                            </a>
                            <a href="export_stock_opname_excel.php?<?= http_build_query($_GET) ?>" class="btn btn-light btn-sm px-3 fw-semibold text-success">
                                <i class="bi bi-file-earmark-excel-fill me-1"></i> Export Excel
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-bordered table-hover mb-0 text-center align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px;">No</th>
                                    <th>Kode Opname</th>
                                    <th class="text-start">Nama Aset</th>
                                    <th>Tgl & Periode</th>
                                    <th class="text-start">Lokasi Cek</th>
                                    <th>Stok Sistem</th>
                                    <th>Stok Fisik</th>
                                    <th>Selisih</th>
                                    <th>Status Selisih</th>
                                    <th>Kondisi Fisik</th>
                                    <th>Pemeriksa</th>
                                    <th style="width: 70px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = 1;
                                if (mysqli_num_rows($res) == 0):
                                ?>
                                    <tr>
                                        <td colspan="12" class="py-4 text-muted">Belum ada data stock opname yang sesuai dengan filter.</td>
                                    </tr>
                                <?php
                                endif;

                                while ($r = mysqli_fetch_assoc($res)):
                                    $selisih = (int)$r['selisih'];
                                    if ($selisih == 0) {
                                        $badge_status = '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="bi bi-check-circle me-1"></i>Sesuai</span>';
                                        $badge_selisih = '<span class="fw-bold text-success">0</span>';
                                    } elseif ($selisih < 0) {
                                        $badge_status = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1"><i class="bi bi-dash-circle me-1"></i>Selisih Kurang</span>';
                                        $badge_selisih = '<span class="fw-bold text-danger">' . $selisih . '</span>';
                                    } else {
                                        $badge_status = '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1"><i class="bi bi-plus-circle me-1"></i>Selisih Lebih</span>';
                                        $badge_selisih = '<span class="fw-bold text-warning-emphasis">+' . $selisih . '</span>';
                                    }

                                    $kondisi_badge = ($r['kondisi_fisik'] == 'Baik') ? 'bg-success' : (($r['kondisi_fisik'] == 'Rusak Ringan') ? 'bg-warning text-dark' : 'bg-danger');
                                ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td class="fw-bold text-secondary"><?= htmlspecialchars($r['kode_opname']) ?></td>
                                        <td class="text-start">
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($r['nama_aset']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($r['kategori_aset'] ?? '') ?></small>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?= date('d/m/Y', strtotime($r['tanggal_opname'])) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($r['periode_opname']) ?></small>
                                        </td>
                                        <td class="text-start"><small><?= htmlspecialchars($r['lokasi_opname']) ?></small></td>
                                        <td class="fw-bold text-primary"><?= $r['stok_sistem'] ?></td>
                                        <td class="fw-bold text-dark"><?= $r['stok_fisik'] ?></td>
                                        <td><?= $badge_selisih ?></td>
                                        <td><?= $badge_status ?></td>
                                        <td><span class="badge <?= $kondisi_badge ?>"><?= htmlspecialchars($r['kondisi_fisik']) ?></span></td>
                                        <td><small><?= htmlspecialchars($r['petugas_opname']) ?></small></td>
                                        <td>
                                            <a href="stock_opname_hapus.php?id=<?= $r['id_opname'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Hapus riwayat opname ini?')" title="Hapus">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-3">
                    <a href="laporan.php" class="btn btn-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i> Kembali ke Menu Utama
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
