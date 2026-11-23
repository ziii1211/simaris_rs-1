<?php
session_start();

if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../login.php");
    exit;
}

include(__DIR__ . '/../config/koneksi.php');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$query = mysqli_query($koneksi, "SELECT * FROM infrastruktur WHERE id_infrastruktur = $id");

if (!$query || mysqli_num_rows($query) == 0) {
    echo "<script>alert('Data infrastruktur tidak ditemukan!'); window.location='infrastruktur.php';</script>";
    exit;
}

$data = mysqli_fetch_assoc($query);
$error = '';

if (isset($_POST['update'])) {
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
        // Cek duplikasi kode selain data ini
        $cekKode = mysqli_query($koneksi, "SELECT id_infrastruktur FROM infrastruktur WHERE kode_infrastruktur = '$kode' AND id_infrastruktur != $id");
        if (mysqli_num_rows($cekKode) > 0) {
            $error = "Kode infrastruktur <b>$kode</b> sudah digunakan pada data lain!";
        } else {
            $update = mysqli_query($koneksi, "UPDATE infrastruktur SET
                kode_infrastruktur = '$kode',
                nama_infrastruktur = '$nama',
                kategori = '$kategori',
                tingkat_prioritas = '$tingkat_prioritas',
                nama_gedung = '$nama_gedung',
                lantai = '$lantai',
                kapasitas = '$kapasitas',
                luas_area = '$luas_area',
                fasilitas = '$fasilitas',
                kondisi = '$kondisi',
                penanggung_jawab = '$penanggung_jawab',
                keterangan = '$keterangan'
                WHERE id_infrastruktur = $id
            ");

            if ($update) {
                header("Location: infrastruktur.php?pesan=edit_sukses");
                exit;
            } else {
                $error = "Gagal memperbarui data: " . mysqli_error($koneksi);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Edit Infrastruktur | SIMARIS RS Bhayangkara</title>
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
                <h3><i class="bi bi-pencil-square me-2"></i> Edit Data Infrastruktur</h3>
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
                                <input type="text" name="kode_infrastruktur" class="form-control" value="<?= htmlspecialchars($_POST['kode_infrastruktur'] ?? $data['kode_infrastruktur']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Kategori Infrastruktur <span class="text-danger">*</span></label>
                                <?php $selectedKat = $_POST['kategori'] ?? $data['kategori']; ?>
                                <select name="kategori" class="form-select" required>
                                    <option value="Gedung" <?= $selectedKat == 'Gedung' ? 'selected' : ''; ?>>Gedung</option>
                                    <option value="Ruangan Pelayanan" <?= $selectedKat == 'Ruangan Pelayanan' ? 'selected' : ''; ?>>Ruangan Pelayanan (IGD/ICU/OK/Poli)</option>
                                    <option value="Kamar Rawat Inap" <?= $selectedKat == 'Kamar Rawat Inap' ? 'selected' : ''; ?>>Kamar Rawat Inap (VVIP/Kelas 1/2/3)</option>
                                    <option value="Instalasi Penunjang" <?= $selectedKat == 'Instalasi Penunjang' ? 'selected' : ''; ?>>Instalasi Penunjang (Farmasi/Lab/Radiologi/IT)</option>
                                </select>
                            </div>

                            <!-- Nama Infrastruktur -->
                            <div class="col-md-12">
                                <label class="form-label">Nama Infrastruktur / Ruangan / Kamar <span class="text-danger">*</span></label>
                                <input type="text" name="nama_infrastruktur" class="form-control" value="<?= htmlspecialchars($_POST['nama_infrastruktur'] ?? $data['nama_infrastruktur']); ?>" required>
                            </div>

                            <!-- Nama Gedung & Lantai -->
                            <div class="col-md-6">
                                <label class="form-label">Lokasi Gedung <span class="text-danger">*</span></label>
                                <input type="text" name="nama_gedung" class="form-control" value="<?= htmlspecialchars($_POST['nama_gedung'] ?? $data['nama_gedung']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Lantai / Posisi <span class="text-danger">*</span></label>
                                <input type="text" name="lantai" class="form-control" value="<?= htmlspecialchars($_POST['lantai'] ?? $data['lantai']); ?>" required>
                            </div>

                            <!-- Kapasitas & Luas Area -->
                            <div class="col-md-6">
                                <label class="form-label">Kapasitas / Jumlah Tempat Tidur (Bed)</label>
                                <input type="text" name="kapasitas" class="form-control" value="<?= htmlspecialchars($_POST['kapasitas'] ?? $data['kapasitas']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Luas Area</label>
                                <input type="text" name="luas_area" class="form-control" value="<?= htmlspecialchars($_POST['luas_area'] ?? $data['luas_area']); ?>">
                            </div>

                            <!-- Fasilitas -->
                            <div class="col-md-12">
                                <label class="form-label">Fasilitas & Sarana Pendukung</label>
                                <textarea name="fasilitas" class="form-control" rows="2"><?= htmlspecialchars($_POST['fasilitas'] ?? $data['fasilitas']); ?></textarea>
                            </div>

                            <!-- Kondisi & Prioritas & Penanggung Jawab -->
                            <div class="col-md-6">
                                <label class="form-label">Kondisi Fisik Saat Ini <span class="text-danger">*</span></label>
                                <?php $selectedKond = $_POST['kondisi'] ?? $data['kondisi']; ?>
                                <select name="kondisi" class="form-select" required>
                                    <option value="Baik" <?= $selectedKond == 'Baik' ? 'selected' : ''; ?>>Baik (Layak Digunakan)</option>
                                    <option value="Rusak Ringan" <?= $selectedKond == 'Rusak Ringan' ? 'selected' : ''; ?>>Rusak Ringan (Butuh Perbaikan Kecil)</option>
                                    <option value="Perlu Renovasi" <?= $selectedKond == 'Perlu Renovasi' ? 'selected' : ''; ?>>Perlu Renovasi / Pemeliharaan Besar</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Tingkat Prioritas / Urgensi Pelayanan <span class="text-danger">*</span></label>
                                <?php $selectedPrio = $_POST['tingkat_prioritas'] ?? $data['tingkat_prioritas'] ?? 'Penting'; ?>
                                <select name="tingkat_prioritas" class="form-select" required>
                                    <option value="Kritis" <?= $selectedPrio == 'Kritis' ? 'selected' : ''; ?>>🔴 Kritis (Infrastruktur Vital IGD/ICU/OK - Respon < 2 Jam)</option>
                                    <option value="Penting" <?= $selectedPrio == 'Penting' ? 'selected' : ''; ?>>🟡 Penting (Fasilitas Rawat Inap & Poliklinik)</option>
                                    <option value="Rutin" <?= $selectedPrio == 'Rutin' ? 'selected' : ''; ?>>⚪ Rutin (Fasilitas Administrasi & Umum)</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Penanggung Jawab / Kepala Ruangan (Karu)</label>
                                <input type="text" name="penanggung_jawab" class="form-control" value="<?= htmlspecialchars($_POST['penanggung_jawab'] ?? $data['penanggung_jawab']); ?>">
                            </div>

                            <!-- Keterangan Tambahan -->
                            <div class="col-md-12">
                                <label class="form-label">Keterangan / Catatan Tambahan</label>
                                <textarea name="keterangan" class="form-control" rows="2"><?= htmlspecialchars($_POST['keterangan'] ?? $data['keterangan']); ?></textarea>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                            <a href="infrastruktur.php" class="btn btn-secondary px-4">Batal</a>
                            <button type="submit" name="update" class="btn btn-theme px-4">
                                <i class="bi bi-check-circle me-1"></i> Perbarui Data
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
