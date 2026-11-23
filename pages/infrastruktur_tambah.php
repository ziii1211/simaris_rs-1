<?php
session_start();

if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../login.php");
    exit;
}

include(__DIR__ . '/../config/koneksi.php');

$error = '';
$success = '';

// Auto generate kode berikutnya
$qAuto = mysqli_query($koneksi, "SELECT id_infrastruktur FROM infrastruktur ORDER BY id_infrastruktur DESC LIMIT 1");
$nextId = 1;
if ($rAuto = mysqli_fetch_assoc($qAuto)) {
    $nextId = (int)$rAuto['id_infrastruktur'] + 1;
}
$defaultKode = 'INF-' . str_pad($nextId, 3, '0', STR_PAD_LEFT);

if (isset($_POST['simpan'])) {
    $kode             = mysqli_real_escape_string($koneksi, trim($_POST['kode_infrastruktur']));
    $nama             = mysqli_real_escape_string($koneksi, trim($_POST['nama_infrastruktur']));
    $kategori         = mysqli_real_escape_string($koneksi, $_POST['kategori']);
    $nama_gedung      = mysqli_real_escape_string($koneksi, trim($_POST['nama_gedung']));
    $lantai           = mysqli_real_escape_string($koneksi, trim($_POST['lantai']));
    $kapasitas        = mysqli_real_escape_string($koneksi, trim($_POST['kapasitas']));
    $luas_area        = mysqli_real_escape_string($koneksi, trim($_POST['luas_area']));
    $fasilitas        = mysqli_real_escape_string($koneksi, trim($_POST['fasilitas']));
    $kondisi          = mysqli_real_escape_string($koneksi, $_POST['kondisi']);
    $tingkat_prioritas = mysqli_real_escape_string($koneksi, $_POST['tingkat_prioritas'] ?? 'Penting');
    $penanggung_jawab = mysqli_real_escape_string($koneksi, trim($_POST['penanggung_jawab']));
    $keterangan       = mysqli_real_escape_string($koneksi, trim($_POST['keterangan']));

    if (empty($kode) || empty($nama) || empty($kategori) || empty($nama_gedung)) {
        $error = "Mohon lengkapi kolom wajib (Kode, Nama, Kategori, dan Gedung)!";
    } else {
        // Cek duplikasi kode
        $cekKode = mysqli_query($koneksi, "SELECT id_infrastruktur FROM infrastruktur WHERE kode_infrastruktur = '$kode'");
        if (mysqli_num_rows($cekKode) > 0) {
            $error = "Kode infrastruktur <b>$kode</b> sudah digunakan, silakan gunakan kode lain!";
        } else {
            $insert = mysqli_query($koneksi, "INSERT INTO infrastruktur (
                kode_infrastruktur, nama_infrastruktur, kategori, tingkat_prioritas, nama_gedung, lantai,
                kapasitas, luas_area, fasilitas, kondisi, penanggung_jawab, keterangan
            ) VALUES (
                '$kode', '$nama', '$kategori', '$tingkat_prioritas', '$nama_gedung', '$lantai',
                '$kapasitas', '$luas_area', '$fasilitas', '$kondisi', '$penanggung_jawab', '$keterangan'
            )");

            if ($insert) {
                header("Location: infrastruktur.php?pesan=tambah_sukses");
                exit;
            } else {
                $error = "Gagal menyimpan data: " . mysqli_error($koneksi);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Tambah Infrastruktur | SIMARIS RS Bhayangkara</title>
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

        .content {
            padding: 30px 40px;
        }

        .card-custom {
            border: none;
            border-radius: 14px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            background: #fff;
        }

        .form-label {
            font-weight: 600;
            font-size: 0.9rem;
            color: #334155;
        }

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
        <?php include(__DIR__ . '/../sidebar.php'); ?>

        <div id="page-content-wrapper">
            <div class="dashboard-header">
                <h3><i class="bi bi-plus-circle me-2"></i> Tambah Data Infrastruktur</h3>
                <a href="infrastruktur.php" class="btn btn-light btn-sm fw-semibold">
                    <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
                </a>
            </div>

            <div class="content">
                <div class="card card-custom p-4" style="max-width: 900px; margin: 0 auto;">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="row g-3">
                            <!-- Kode & Kategori -->
                            <div class="col-md-6">
                                <label class="form-label">Kode Infrastruktur <span class="text-danger">*</span></label>
                                <input type="text" name="kode_infrastruktur" class="form-control" value="<?= htmlspecialchars($_POST['kode_infrastruktur'] ?? $defaultKode); ?>" required>
                                <small class="text-muted">Contoh: INF-GDG-001 (Gedung), INF-RNG-002 (Ruangan), INF-KMR-003 (Kamar)</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Kategori Infrastruktur <span class="text-danger">*</span></label>
                                <select name="kategori" class="form-select" required>
                                    <option value="">-- Pilih Kategori --</option>
                                    <option value="Gedung" <?= (($_POST['kategori'] ?? '') == 'Gedung') ? 'selected' : ''; ?>>Gedung</option>
                                    <option value="Ruangan Pelayanan" <?= (($_POST['kategori'] ?? '') == 'Ruangan Pelayanan') ? 'selected' : ''; ?>>Ruangan Pelayanan (IGD/ICU/OK/Poli)</option>
                                    <option value="Kamar Rawat Inap" <?= (($_POST['kategori'] ?? '') == 'Kamar Rawat Inap') ? 'selected' : ''; ?>>Kamar Rawat Inap (VVIP/Kelas 1/2/3)</option>
                                    <option value="Instalasi Penunjang" <?= (($_POST['kategori'] ?? '') == 'Instalasi Penunjang') ? 'selected' : ''; ?>>Instalasi Penunjang (Farmasi/Lab/Radiologi/IT)</option>
                                </select>
                            </div>

                            <!-- Nama Infrastruktur -->
                            <div class="col-md-12">
                                <label class="form-label">Nama Infrastruktur / Ruangan / Kamar <span class="text-danger">*</span></label>
                                <input type="text" name="nama_infrastruktur" class="form-control" placeholder="Contoh: Kamar Rawat Inap Kelas 1 Anggrek 201" value="<?= htmlspecialchars($_POST['nama_infrastruktur'] ?? ''); ?>" required>
                            </div>

                            <!-- Nama Gedung & Lantai -->
                            <div class="col-md-6">
                                <label class="form-label">Lokasi Gedung <span class="text-danger">*</span></label>
                                <input type="text" name="nama_gedung" class="form-control" placeholder="Contoh: Gedung Presisi / Gedung Rawat Inap" value="<?= htmlspecialchars($_POST['nama_gedung'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Lantai / Posisi <span class="text-danger">*</span></label>
                                <input type="text" name="lantai" class="form-control" placeholder="Contoh: Lantai 2 / Lantai Dasar" value="<?= htmlspecialchars($_POST['lantai'] ?? 'Lantai 1'); ?>" required>
                            </div>

                            <!-- Kapasitas & Luas Area -->
                            <div class="col-md-6">
                                <label class="form-label">Kapasitas / Jumlah Tempat Tidur (Bed)</label>
                                <input type="text" name="kapasitas" class="form-control" placeholder="Contoh: 4 Tempat Tidur Pasien" value="<?= htmlspecialchars($_POST['kapasitas'] ?? ''); ?>">
                                <small class="text-muted">Untuk kamar inap sebutkan jumlah bed; untuk ruangan sebutkan kapasitas orang</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Luas Area</label>
                                <input type="text" name="luas_area" class="form-control" placeholder="Contoh: 36 m² (6 x 6 meter)" value="<?= htmlspecialchars($_POST['luas_area'] ?? ''); ?>">
                            </div>

                            <!-- Fasilitas -->
                            <div class="col-md-12">
                                <label class="form-label">Fasilitas & Sarana Pendukung</label>
                                <textarea name="fasilitas" class="form-control" rows="2" placeholder="Contoh: AC 1 PK, Oksigen Sentral, Bed Pasien Crank, Kamar Mandi Dalam, Bel Panggil Perawat (Nurse Call), TV"><?= htmlspecialchars($_POST['fasilitas'] ?? ''); ?></textarea>
                            </div>

                            <!-- Kondisi & Prioritas & Penanggung Jawab -->
                            <div class="col-md-6">
                                <label class="form-label">Kondisi Fisik Saat Ini <span class="text-danger">*</span></label>
                                <select name="kondisi" class="form-select" required>
                                    <option value="Baik" <?= (($_POST['kondisi'] ?? '') == 'Baik') ? 'selected' : ''; ?>>Baik (Layak Digunakan)</option>
                                    <option value="Rusak Ringan" <?= (($_POST['kondisi'] ?? '') == 'Rusak Ringan') ? 'selected' : ''; ?>>Rusak Ringan (Butuh Perbaikan Kecil)</option>
                                    <option value="Perlu Renovasi" <?= (($_POST['kondisi'] ?? '') == 'Perlu Renovasi') ? 'selected' : ''; ?>>Perlu Renovasi / Pemeliharaan Besar</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Tingkat Prioritas / Urgensi Pelayanan <span class="text-danger">*</span></label>
                                <select name="tingkat_prioritas" class="form-select" required>
                                    <option value="Kritis" <?= (($_POST['tingkat_prioritas'] ?? '') == 'Kritis') ? 'selected' : ''; ?>>🔴 Kritis (Infrastruktur Vital IGD/ICU/OK - Respon < 2 Jam)</option>
                                    <option value="Penting" <?= (($_POST['tingkat_prioritas'] ?? 'Penting') == 'Penting') ? 'selected' : ''; ?>>🟡 Penting (Fasilitas Rawat Inap & Poliklinik)</option>
                                    <option value="Rutin" <?= (($_POST['tingkat_prioritas'] ?? '') == 'Rutin') ? 'selected' : ''; ?>>⚪ Rutin (Fasilitas Administrasi & Umum)</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Penanggung Jawab / Kepala Ruangan (Karu)</label>
                                <input type="text" name="penanggung_jawab" class="form-control" placeholder="Contoh: Ns. Siti Rahmah, S.Kep" value="<?= htmlspecialchars($_POST['penanggung_jawab'] ?? ''); ?>">
                            </div>

                            <!-- Keterangan Tambahan -->
                            <div class="col-md-12">
                                <label class="form-label">Keterangan / Catatan Tambahan</label>
                                <textarea name="keterangan" class="form-control" rows="2" placeholder="Catatan status kelayakan, jadwal renovasi berkala, dsb."><?= htmlspecialchars($_POST['keterangan'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                            <a href="infrastruktur.php" class="btn btn-secondary px-4">Batal</a>
                            <button type="submit" name="simpan" class="btn btn-theme px-4">
                                <i class="bi bi-save me-1"></i> Simpan Data Infrastruktur
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
