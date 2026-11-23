<?php
session_start();
if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../login.php");
    exit;
}

include(__DIR__ . '/../config/koneksi.php');
include(__DIR__ . '/../header.php');
?>

<style>
    .filter-form input,
    .filter-form select {
        border-radius: 8px;
        padding: 6px 10px;
        border: 1px solid #ccc;
        font-size: 0.85rem;
    }

    .card-header {
        background: linear-gradient(90deg, #2c7a7b, #1cc88a);
        color: #fff;
        font-weight: 600;
    }

    .stats {
        display: flex;
        gap: 12px;
        margin-bottom: 15px;
        flex-wrap: wrap;
    }

    .stat {
        background: #fff;
        padding: 10px 16px;
        border-radius: 8px;
        box-shadow: 0 1px 4px rgba(0, 0, 0, .05);
        font-size: 0.9rem;
        border-left: 4px solid #2c7a7b;
    }

    .badge-gedung { background-color: #3b82f6; color: #fff; }
    .badge-ruangan { background-color: #0d9488; color: #fff; }
    .badge-kamar { background-color: #8b5cf6; color: #fff; }
    .badge-penunjang { background-color: #f59e0b; color: #fff; }
</style>

<div class="dashboard-header">
    <div>
        <h3 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i> Laporan Infrastruktur Gedung & Kamar</h3>
        <small class="text-white-50">Rekapitulasi Fisik Gedung, Ruangan Pelayanan, Kamar Rawat Inap & Fasilitas RS Bhayangkara</small>
    </div>
</div>

<div class="content">
    <?php
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $perPage = 10;

    $search   = isset($_GET['search']) ? mysqli_real_escape_string($koneksi, trim($_GET['search'])) : '';
    $kategori = isset($_GET['kategori']) ? mysqli_real_escape_string($koneksi, $_GET['kategori']) : '';
    $kondisi  = isset($_GET['kondisi']) ? mysqli_real_escape_string($koneksi, $_GET['kondisi']) : '';
    $gedung   = isset($_GET['gedung']) ? mysqli_real_escape_string($koneksi, $_GET['gedung']) : '';

    $where = [];
    if ($search !== '')   $where[] = "(kode_infrastruktur LIKE '%$search%' OR nama_infrastruktur LIKE '%$search%' OR fasilitas LIKE '%$search%' OR penanggung_jawab LIKE '%$search%')";
    if ($kategori !== '') $where[] = "kategori = '$kategori'";
    if ($kondisi !== '')  $where[] = "kondisi = '$kondisi'";
    if ($gedung !== '')   $where[] = "nama_gedung = '$gedung'";

    $whereSQL = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Statistik
    $statQ = mysqli_query($koneksi, "SELECT 
        COUNT(*) as total_all,
        SUM(kategori = 'Gedung') as total_gedung,
        SUM(kategori = 'Ruangan Pelayanan') as total_ruangan,
        SUM(kategori = 'Kamar Rawat Inap') as total_kamar,
        SUM(kondisi = 'Baik') as total_baik,
        SUM(kondisi = 'Perlu Renovasi') as total_renovasi
    FROM infrastruktur $whereSQL");
    $stat = mysqli_fetch_assoc($statQ);

    $countQ = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM infrastruktur $whereSQL");
    $totalRow = mysqli_fetch_assoc($countQ)['total'] ?? 0;
    $offset = ($page - 1) * $perPage;

    $dataQ = mysqli_query($koneksi, "SELECT * FROM infrastruktur $whereSQL ORDER BY id_infrastruktur DESC LIMIT $offset, $perPage");

    // Daftar Gedung untuk Filter
    $qGedungList = mysqli_query($koneksi, "SELECT DISTINCT nama_gedung FROM infrastruktur WHERE nama_gedung != '' ORDER BY nama_gedung ASC");
    ?>

    <!-- Form Filter & Tombol Cetak -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <form class="d-flex gap-2 filter-form flex-wrap" method="GET" action="">
            <input type="text" name="search" placeholder="Cari infrastruktur..." value="<?= htmlspecialchars($search); ?>">
            <select name="kategori">
                <option value="">Semua Kategori</option>
                <option value="Gedung" <?= $kategori == 'Gedung' ? 'selected' : ''; ?>>Gedung</option>
                <option value="Ruangan Pelayanan" <?= $kategori == 'Ruangan Pelayanan' ? 'selected' : ''; ?>>Ruangan Pelayanan</option>
                <option value="Kamar Rawat Inap" <?= $kategori == 'Kamar Rawat Inap' ? 'selected' : ''; ?>>Kamar Rawat Inap</option>
                <option value="Instalasi Penunjang" <?= $kategori == 'Instalasi Penunjang' ? 'selected' : ''; ?>>Instalasi Penunjang</option>
            </select>
            <select name="gedung">
                <option value="">Semua Gedung</option>
                <?php while ($g = mysqli_fetch_assoc($qGedungList)): ?>
                    <option value="<?= htmlspecialchars($g['nama_gedung']); ?>" <?= $gedung == $g['nama_gedung'] ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($g['nama_gedung']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <select name="kondisi">
                <option value="">Semua Kondisi</option>
                <option value="Baik" <?= $kondisi == 'Baik' ? 'selected' : ''; ?>>Baik</option>
                <option value="Rusak Ringan" <?= $kondisi == 'Rusak Ringan' ? 'selected' : ''; ?>>Rusak Ringan</option>
                <option value="Perlu Renovasi" <?= $kondisi == 'Perlu Renovasi' ? 'selected' : ''; ?>>Perlu Renovasi</option>
            </select>
            <button class="btn btn-success btn-sm" type="submit"><i class="bi bi-search"></i> Filter</button>
            <a href="laporan_infrastruktur.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-counterclockwise"></i></a>
        </form>

        <div class="d-flex gap-2">
            <a href="export_infrastruktur_excel.php?<?= http_build_query($_GET); ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i> Excel
            </a>
            <a href="cetak_laporan_infrastruktur.php?<?= http_build_query($_GET); ?>" class="btn btn-danger btn-sm" target="_blank">
                <i class="bi bi-printer me-1"></i> Cetak PDF
            </a>
        </div>
    </div>

    <!-- Statistik Ringkas -->
    <div class="stats">
        <div class="stat">Total Data: <strong><?= intval($stat['total_all']); ?></strong></div>
        <div class="stat">Gedung: <strong><?= intval($stat['total_gedung']); ?></strong></div>
        <div class="stat">Ruang Pelayanan: <strong><?= intval($stat['total_ruangan']); ?></strong></div>
        <div class="stat">Kamar Inap: <strong><?= intval($stat['total_kamar']); ?></strong></div>
        <div class="stat">Kondisi Baik: <strong class="text-success"><?= intval($stat['total_baik']); ?></strong></div>
        <div class="stat">Perlu Renovasi: <strong class="text-danger"><?= intval($stat['total_renovasi']); ?></strong></div>
    </div>

    <!-- Tabel Data Rekapitulasi -->
    <div class="card shadow-sm border-0">
        <div class="card-header py-3">
            <i class="bi bi-table me-2"></i> Rekapitulasi Data Infrastruktur Rumah Sakit
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover table-bordered mb-0 align-middle">
                <thead class="table-light text-center">
                    <tr>
                        <th width="40">No</th>
                        <th width="110">Kode</th>
                        <th>Nama Infrastruktur</th>
                        <th>Kategori</th>
                        <th>Gedung & Lantai</th>
                        <th>Kapasitas / Bed</th>
                        <th>Luas Area</th>
                        <th>Kondisi</th>
                        <th>Penanggung Jawab</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($dataQ) == 0): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
                                Tidak ada data infrastruktur yang ditemukan.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php
                        $no = $offset + 1;
                        while ($r = mysqli_fetch_assoc($dataQ)):
                            $katBadge = match ($r['kategori']) {
                                'Gedung' => 'badge-gedung',
                                'Ruangan Pelayanan' => 'badge-ruangan',
                                'Kamar Rawat Inap' => 'badge-kamar',
                                default => 'badge-penunjang'
                            };

                            $kondBadge = match ($r['kondisi']) {
                                'Baik' => 'bg-success',
                                'Rusak Ringan' => 'bg-warning text-dark',
                                default => 'bg-danger'
                            };
                        ?>
                            <tr>
                                <td class="text-center"><?= $no++; ?></td>
                                <td class="text-center fw-bold text-primary font-monospace"><?= htmlspecialchars($r['kode_infrastruktur']); ?></td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($r['nama_infrastruktur']); ?></div>
                                    <small class="text-muted d-block text-truncate" style="max-width: 250px;">
                                        Fasilitas: <?= htmlspecialchars($r['fasilitas'] ?? '-'); ?>
                                    </small>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= $katBadge; ?>"><?= htmlspecialchars($r['kategori']); ?></span>
                                </td>
                                <td><?= htmlspecialchars($r['nama_gedung'] . ' - ' . $r['lantai']); ?></td>
                                <td class="text-center"><?= htmlspecialchars($r['kapasitas'] ?? '-'); ?></td>
                                <td class="text-center"><?= htmlspecialchars($r['luas_area'] ?? '-'); ?></td>
                                <td class="text-center">
                                    <span class="badge <?= $kondBadge; ?>"><?= htmlspecialchars($r['kondisi']); ?></span>
                                </td>
                                <td><?= htmlspecialchars($r['penanggung_jawab'] ?? '-'); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include(__DIR__ . '/../footer.php'); ?>
