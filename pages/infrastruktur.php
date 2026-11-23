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

// ================= FILTER & PENCARIAN =================
$search = isset($_GET['search']) ? mysqli_real_escape_string($koneksi, trim($_GET['search'])) : "";
$kategori = isset($_GET['kategori']) ? mysqli_real_escape_string($koneksi, $_GET['kategori']) : "";
$kondisi = isset($_GET['kondisi']) ? mysqli_real_escape_string($koneksi, $_GET['kondisi']) : "";
$gedung = isset($_GET['gedung']) ? mysqli_real_escape_string($koneksi, $_GET['gedung']) : "";
$prioritas = isset($_GET['prioritas']) ? mysqli_real_escape_string($koneksi, $_GET['prioritas']) : "";

$where = [];
if ($search != '') {
    $where[] = "(kode_infrastruktur LIKE '%$search%' OR nama_infrastruktur LIKE '%$search%' OR fasilitas LIKE '%$search%' OR penanggung_jawab LIKE '%$search%')";
}
if ($kategori != '') {
    $where[] = "kategori = '$kategori'";
}
if ($kondisi != '') {
    $where[] = "kondisi = '$kondisi'";
}
if ($gedung != '') {
    $where[] = "nama_gedung = '$gedung'";
}
if ($prioritas != '') {
    $where[] = "tingkat_prioritas = '$prioritas'";
}

$whereSQL = count($where) ? "WHERE " . implode(" AND ", $where) : "";

// ================= STATISTIK =================
$statQ = mysqli_query($koneksi, "SELECT 
    COUNT(*) AS total_all,
    SUM(kategori = 'Gedung') AS total_gedung,
    SUM(kategori = 'Ruangan Pelayanan') AS total_ruangan,
    SUM(kategori = 'Kamar Rawat Inap') AS total_kamar,
    SUM(kategori = 'Instalasi Penunjang') AS total_penunjang,
    SUM(kondisi = 'Baik') AS total_baik,
    SUM(kondisi = 'Rusak Ringan') AS total_rusak_ringan,
    SUM(kondisi = 'Perlu Renovasi') AS total_renovasi,
    SUM(tingkat_prioritas = 'Kritis') AS total_kritis
FROM infrastruktur");
$stat = mysqli_fetch_assoc($statQ);

// Query Gedung Unik untuk Dropdown Filter
$qGedungList = mysqli_query($koneksi, "SELECT DISTINCT nama_gedung FROM infrastruktur WHERE nama_gedung != '' ORDER BY nama_gedung ASC");

// ================= PAGINATION =================
$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$countRes = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM infrastruktur $whereSQL");
$totalData = mysqli_fetch_assoc($countRes)['total'] ?? 0;
$totalPages = ceil($totalData / $limit);

$sql = "SELECT * FROM infrastruktur $whereSQL ORDER BY FIELD(tingkat_prioritas, 'Kritis', 'Penting', 'Rutin') ASC, id_infrastruktur DESC LIMIT $offset, $limit";
$res = mysqli_query($koneksi, $sql);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Infrastruktur Gedung & Kamar | SIMARIS RS Bhayangkara</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f4f6f9;
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
        }

        .dashboard-header {
            background: linear-gradient(90deg, #2c7a7b, #1cc88a);
            color: #fff;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #fff;
        }

        .dashboard-header h3 {
            margin: 0;
            font-size: 1.6rem;
            font-weight: 700;
        }

        .content {
            padding: 25px 30px 40px 30px;
        }

        /* Stat Cards */
        .stat-box {
            background: #fff;
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
            border-left: 5px solid #2c7a7b;
            transition: transform 0.2s ease;
        }

        .stat-box:hover {
            transform: translateY(-3px);
        }

        .stat-box h6 {
            color: #64748b;
            font-size: 0.85rem;
            margin-bottom: 6px;
            font-weight: 600;
        }

        .stat-box h3 {
            margin: 0;
            font-weight: 700;
            color: #1e293b;
        }

        .card-custom {
            border: none;
            border-radius: 14px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            background: #fff;
            overflow: hidden;
        }

        .card-header-custom {
            background: linear-gradient(90deg, #2c7a7b, #1cc88a);
            color: #fff;
            padding: 14px 20px;
            font-weight: 600;
            font-size: 1.05rem;
        }

        .table-custom thead th {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            border-bottom: 2px solid #cbd5e1;
            padding: 12px 10px;
            vertical-align: middle;
        }

        .table-custom tbody td {
            font-size: 0.88rem;
            padding: 12px 10px;
            vertical-align: middle;
        }

        .badge-gedung { background-color: #3b82f6; color: #fff; }
        .badge-ruangan { background-color: #0d9488; color: #fff; }
        .badge-kamar { background-color: #8b5cf6; color: #fff; }
        .badge-penunjang { background-color: #f59e0b; color: #fff; }

        .btn-theme {
            background: linear-gradient(90deg, #2c7a7b, #1cc88a);
            color: #fff;
            border: none;
            font-weight: 600;
        }

        .btn-theme:hover {
            opacity: 0.92;
            color: #fff;
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
                    <h3><i class="bi bi-building me-2"></i> Manajemen Infrastruktur Rumah Sakit</h3>
                    <small style="opacity: 0.9;">Pendataan Fisik Gedung, Ruangan Pelayanan, Kamar Rawat Inap & Fasilitas RS</small>
                </div>
                <div>
                    <span class="badge bg-light text-dark px-3 py-2 fw-semibold">
                        <i class="bi bi-person-check-fill text-success me-1"></i>
                        <?= htmlspecialchars($_SESSION['nama_pengguna']); ?> (<?= ucfirst($_SESSION['level']); ?>)
                    </span>
                </div>
            </div>

            <!-- CONTENT -->
            <div class="content">

                <!-- 1. KARTU STATISTIK -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-box" style="border-left-color: #3b82f6;">
                            <h6><i class="bi bi-buildings text-primary me-1"></i> TOTAL GEDUNG</h6>
                            <h3><?= intval($stat['total_gedung']); ?> <span style="font-size:0.9rem; font-weight:normal; color:#64748b;">Unit Gedung</span></h3>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-box" style="border-left-color: #0d9488;">
                            <h6><i class="bi bi-hospital text-teal me-1"></i> RUANGAN PELAYANAN</h6>
                            <h3><?= intval($stat['total_ruangan']); ?> <span style="font-size:0.9rem; font-weight:normal; color:#64748b;">Ruang (IGD/ICU/Poli)</span></h3>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-box" style="border-left-color: #8b5cf6;">
                            <h6><i class="bi bi-door-open text-purple me-1"></i> KAMAR RAWAT INAP</h6>
                            <h3><?= intval($stat['total_kamar']); ?> <span style="font-size:0.9rem; font-weight:normal; color:#64748b;">Kamar Inap</span></h3>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-box" style="border-left-color: #10b981;">
                            <h6><i class="bi bi-check-circle text-success me-1"></i> KONDISI BAIK</h6>
                            <h3><?= intval($stat['total_baik']); ?> / <?= intval($stat['total_all']); ?> <span style="font-size:0.9rem; font-weight:normal; color:#64748b;">Layak Pakai</span></h3>
                        </div>
                    </div>
                </div>

                <!-- 2. FILTER & AKSI CEPAT -->
                <div class="card card-custom mb-4">
                    <div class="card-body p-3">
                        <form method="GET" action="" class="row g-2 align-items-center">
                            <div class="col-md-3">
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="🔍 Cari nama/kode/fasilitas..." value="<?= htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2">
                                <select name="kategori" class="form-select form-select-sm">
                                    <option value="">-- Semua Kategori --</option>
                                    <option value="Gedung" <?= $kategori == 'Gedung' ? 'selected' : ''; ?>>Gedung</option>
                                    <option value="Ruangan Pelayanan" <?= $kategori == 'Ruangan Pelayanan' ? 'selected' : ''; ?>>Ruangan Pelayanan</option>
                                    <option value="Kamar Rawat Inap" <?= $kategori == 'Kamar Rawat Inap' ? 'selected' : ''; ?>>Kamar Rawat Inap</option>
                                    <option value="Instalasi Penunjang" <?= $kategori == 'Instalasi Penunjang' ? 'selected' : ''; ?>>Instalasi Penunjang</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="gedung" class="form-select form-select-sm">
                                    <option value="">-- Semua Gedung --</option>
                                    <?php while ($g = mysqli_fetch_assoc($qGedungList)): ?>
                                        <option value="<?= htmlspecialchars($g['nama_gedung']); ?>" <?= $gedung == $g['nama_gedung'] ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($g['nama_gedung']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="kondisi" class="form-select form-select-sm">
                                    <option value="">-- Semua Kondisi --</option>
                                    <option value="Baik" <?= $kondisi == 'Baik' ? 'selected' : ''; ?>>Baik</option>
                                    <option value="Rusak Ringan" <?= $kondisi == 'Rusak Ringan' ? 'selected' : ''; ?>>Rusak Ringan</option>
                                    <option value="Perlu Renovasi" <?= $kondisi == 'Perlu Renovasi' ? 'selected' : ''; ?>>Perlu Renovasi</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="prioritas" class="form-select form-select-sm">
                                    <option value="">-- Semua Prioritas --</option>
                                    <option value="Kritis" <?= $prioritas == 'Kritis' ? 'selected' : ''; ?>>🔴 Kritis (Vital)</option>
                                    <option value="Penting" <?= $prioritas == 'Penting' ? 'selected' : ''; ?>>🟡 Penting</option>
                                    <option value="Rutin" <?= $prioritas == 'Rutin' ? 'selected' : ''; ?>>⚪ Rutin</option>
                                </select>
                            </div>
                            <div class="col-md-1 d-flex gap-1">
                                <button type="submit" class="btn btn-sm btn-theme flex-fill" title="Terapkan Filter"><i class="bi bi-filter"></i></button>
                                <a href="infrastruktur.php" class="btn btn-sm btn-outline-secondary" title="Reset Filter"><i class="bi bi-arrow-counterclockwise"></i></a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 3. TABEL DATA INFRASTRUKTUR -->
                <div class="card card-custom">
                    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span><i class="bi bi-table me-2"></i> Daftar Infrastruktur Fisik Rumah Sakit</span>
                        <div class="d-flex gap-2 align-items-center flex-wrap">
                            <button type="button" class="btn btn-warning btn-sm fw-bold text-dark shadow-sm" data-bs-toggle="modal" data-bs-target="#modalMatriksPerbedaan">
                                <i class="bi bi-diagram-3-fill me-1"></i> Perbedaan Aset vs Infrastruktur
                            </button>
                            <a href="infrastruktur_tambah.php" class="btn btn-light btn-sm fw-bold text-success shadow-sm">
                                <i class="bi bi-plus-circle me-1"></i> Tambah Data
                            </a>
                            <a href="export_infrastruktur_excel.php?<?= http_build_query($_GET); ?>" class="btn btn-outline-light btn-sm">
                                <i class="bi bi-file-earmark-excel me-1"></i> Excel
                            </a>
                            <a href="infrastruktur_cetak.php?<?= http_build_query($_GET); ?>" target="_blank" class="btn btn-danger btn-sm shadow-sm">
                                <i class="bi bi-printer me-1"></i> Cetak PDF
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-hover table-bordered table-custom mb-0 align-middle">
                            <thead>
                                <tr class="text-center">
                                    <th width="40">No</th>
                                    <th width="110">Kode</th>
                                    <th>Nama Infrastruktur</th>
                                    <th width="105">Prioritas</th>
                                    <th width="130">Kategori</th>
                                    <th>Lokasi Gedung</th>
                                    <th>Kapasitas</th>
                                    <th width="135" class="bg-primary text-white"><i class="bi bi-box-seam me-1"></i> Aset di Ruang</th>
                                    <th width="100">Kondisi</th>
                                    <th>Penanggung Jawab</th>
                                    <th width="120">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($res) == 0): ?>
                                    <tr>
                                        <td colspan="11" class="text-center py-4 text-muted">
                                            <i class="bi bi-folder-x fs-2 d-block mb-2 text-secondary"></i>
                                            Tidak ada data infrastruktur yang sesuai kriteria pencarian.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php
                                    $no = $offset + 1;
                                    while ($row = mysqli_fetch_assoc($res)):
                                        $prioI = $row['tingkat_prioritas'] ?? 'Penting';
                                        $isKritisI = ($prioI === 'Kritis');

                                        // Badge Kategori
                                        $katBadge = match ($row['kategori']) {
                                            'Gedung' => 'badge-gedung',
                                            'Ruangan Pelayanan' => 'badge-ruangan',
                                            'Kamar Rawat Inap' => 'badge-kamar',
                                            default => 'badge-penunjang'
                                        };

                                        // Badge Kondisi
                                        $kondisiBadge = match ($row['kondisi']) {
                                            'Baik' => 'bg-success',
                                            'Rusak Ringan' => 'bg-warning text-dark',
                                            default => 'bg-danger'
                                        };

                                        // Hitung Aset di dalam Ruangan ini (Relasi Parent-Child)
                                        $namaInf = $row['nama_infrastruktur'];
                                        $tks = [];
                                        if (stripos($namaInf, 'IGD') !== false) $tks[] = "lokasi LIKE '%IGD%'";
                                        if (stripos($namaInf, 'ICU') !== false) $tks[] = "lokasi LIKE '%ICU%'";
                                        if (stripos($namaInf, 'Operasi') !== false || stripos($namaInf, 'OK') !== false) $tks[] = "lokasi LIKE '%Operasi%'";
                                        if (stripos($namaInf, 'VVIP') !== false) $tks[] = "lokasi LIKE '%VVIP%'";
                                        if (stripos($namaInf, 'Kelas 1') !== false) $tks[] = "lokasi LIKE '%Kelas 1%'";
                                        if (stripos($namaInf, 'Kelas 2') !== false) $tks[] = "lokasi LIKE '%Kelas 2%'";
                                        if (stripos($namaInf, 'Kelas 3') !== false) $tks[] = "lokasi LIKE '%Kelas 3%'";
                                        if (stripos($namaInf, 'Gigi') !== false) $tks[] = "lokasi LIKE '%Gigi%'";
                                        if (stripos($namaInf, 'Farmasi') !== false) $tks[] = "lokasi LIKE '%Farmasi%'";
                                        if (stripos($namaInf, 'Radiologi') !== false) $tks[] = "lokasi LIKE '%Radiologi%'";
                                        if (stripos($namaInf, 'Server') !== false || stripos($namaInf, 'IT') !== false) $tks[] = "lokasi LIKE '%Server%' OR lokasi LIKE '%IT%'";

                                        if ($row['kategori'] === 'Gedung') {
                                            $whm = (stripos($namaInf, 'Utama') !== false) ? "(lokasi LIKE '%Administrasi%' OR lokasi LIKE '%Gudang Utama%' OR lokasi LIKE '%Poli%')" : "(lokasi LIKE '%Rawat Inap%' OR lokasi LIKE '%ICU%' OR lokasi LIKE '%Operasi%')";
                                        } elseif (!empty($tks)) {
                                            $whm = "(" . implode(" OR ", $tks) . " OR lokasi = '" . mysqli_real_escape_string($koneksi, $namaInf) . "')";
                                        } else {
                                            $whm = "lokasi LIKE '%" . mysqli_real_escape_string($koneksi, $namaInf) . "%'";
                                        }

                                        $qCntA = mysqli_query($koneksi, "SELECT COUNT(*) as cnt, SUM(total_stok) as tot_u FROM aset WHERE $whm");
                                        $rCntA = mysqli_fetch_assoc($qCntA);
                                        $cntJenis = (int)($rCntA['cnt'] ?? 0);
                                        $cntUnit = (int)($rCntA['tot_u'] ?? 0);
                                    ?>
                                        <tr style="<?= $isKritisI ? 'background-color: #fff9f9;' : ''; ?>">
                                            <td class="text-center"><?= $no++; ?></td>
                                            <td class="text-center fw-semibold text-primary font-monospace"><?= htmlspecialchars($row['kode_infrastruktur']); ?></td>
                                            <td>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($row['nama_infrastruktur']); ?></div>
                                                <small class="text-muted"><i class="bi bi-aspect-ratio me-1"></i>Luas: <?= htmlspecialchars($row['luas_area'] ?? '-'); ?></small>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($prioI === 'Kritis'): ?>
                                                    <span class="badge bg-danger shadow-sm"><i class="bi bi-exclamation-triangle-fill"></i> Kritis</span>
                                                <?php elseif ($prioI === 'Penting'): ?>
                                                    <span class="badge bg-warning text-dark"><i class="bi bi-shield"></i> Penting</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary"><i class="bi bi-check-circle"></i> Rutin</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge <?= $katBadge; ?> px-2 py-1"><?= htmlspecialchars($row['kategori']); ?></span>
                                            </td>
                                            <td>
                                                <div class="fw-medium"><?= htmlspecialchars($row['nama_gedung']); ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($row['lantai']); ?></small>
                                            </td>
                                            <td class="text-center">
                                                <?= !empty($row['kapasitas']) ? htmlspecialchars($row['kapasitas']) : '<span class="text-muted">-</span>'; ?>
                                            </td>
                                            <td class="text-center">
                                                <a href="infrastruktur_detail.php?id=<?= $row['id_infrastruktur']; ?>" class="btn btn-sm btn-info text-white fw-semibold py-1 px-2 shadow-sm text-nowrap" title="Lihat Daftar Barang/Alkes di Ruangan Ini">
                                                    <i class="bi bi-box-seam me-1"></i> <?= $cntJenis; ?> Aset (<?= $cntUnit; ?> Unit)
                                                </a>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge <?= $kondisiBadge; ?> px-2 py-1"><?= htmlspecialchars($row['kondisi']); ?></span>
                                            </td>
                                            <td>
                                                <small class="fw-medium text-dark"><?= htmlspecialchars($row['penanggung_jawab'] ?? '-'); ?></small>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="infrastruktur_detail.php?id=<?= $row['id_infrastruktur']; ?>" class="btn btn-outline-info" title="Detail Ruangan & Aset">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="infrastruktur_edit.php?id=<?= $row['id_infrastruktur']; ?>" class="btn btn-outline-primary" title="Edit Data">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </a>
                                                    <a href="infrastruktur_hapus.php?id=<?= $row['id_infrastruktur']; ?>" class="btn btn-outline-danger" title="Hapus Data" onclick="return confirm('Apakah Anda yakin ingin menghapus data <?= htmlspecialchars(addslashes($row['nama_infrastruktur'])); ?>?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- PAGINATION -->
                    <?php if ($totalPages > 1): ?>
                        <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
                            <small class="text-muted">Menampilkan <?= min($totalData, $offset + 1); ?> - <?= min($totalData, $offset + $limit); ?> dari <?= $totalData; ?> data</small>
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?= $page == $i ? 'active' : ''; ?>">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?= $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <!-- INCLUDE MODAL MATRIKS PERBEDAAN ASET VS INFRASTRUKTUR -->
    <?php include(__DIR__ . '/modal_perbedaan_aset_infrastruktur.php'); ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
