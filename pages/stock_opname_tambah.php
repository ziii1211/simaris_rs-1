<?php
session_start();
if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../login.php");
    exit;
}

include(__DIR__ . '/../config/koneksi.php');

$error = '';
$success = '';

// Generate Auto Code SO-YYYY-XXX
$thn_skrg = date('Y');
$qLast = mysqli_query($koneksi, "SELECT kode_opname FROM stock_opname WHERE kode_opname LIKE 'SO-$thn_skrg-%' ORDER BY id_opname DESC LIMIT 1");
if ($rLast = mysqli_fetch_assoc($qLast)) {
    $lastNum = (int)substr($rLast['kode_opname'], -3);
    $nextCode = sprintf("SO-%s-%03d", $thn_skrg, $lastNum + 1);
} else {
    $nextCode = sprintf("SO-%s-001", $thn_skrg);
}

// Ambil daftar aset
$qAset = mysqli_query($koneksi, "SELECT id_aset, nama_aset, total_stok, lokasi, kategori_aset FROM aset ORDER BY nama_aset ASC");

// Proses Form Submit
if (isset($_POST['simpan'])) {
    $kode_opname    = mysqli_real_escape_string($koneksi, trim($_POST['kode_opname']));
    $id_aset        = (int)$_POST['id_aset'];
    $tanggal_opname = mysqli_real_escape_string($koneksi, trim($_POST['tanggal_opname']));
    $periode_opname = mysqli_real_escape_string($koneksi, trim($_POST['periode_opname']));
    $lokasi_opname  = mysqli_real_escape_string($koneksi, trim($_POST['lokasi_opname']));
    $stok_sistem    = (int)$_POST['stok_sistem'];
    $stok_fisik     = (int)$_POST['stok_fisik'];
    $kondisi_fisik  = mysqli_real_escape_string($koneksi, trim($_POST['kondisi_fisik']));
    $petugas_opname = mysqli_real_escape_string($koneksi, trim($_POST['petugas_opname']));
    $keterangan     = mysqli_real_escape_string($koneksi, trim($_POST['keterangan']));

    $selisih = $stok_fisik - $stok_sistem;
    if ($selisih == 0) {
        $status_selisih = 'Sesuai';
    } elseif ($selisih < 0) {
        $status_selisih = 'Selisih Kurang';
    } else {
        $status_selisih = 'Selisih Lebih';
    }

    if (empty($kode_opname) || empty($id_aset) || empty($tanggal_opname) || empty($petugas_opname)) {
        $error = "Silakan lengkapi seluruh kolom yang bertanda bintang (*)!";
    } else {
        $stmt = mysqli_prepare($koneksi, "
            INSERT INTO stock_opname (
                kode_opname, id_aset, tanggal_opname, periode_opname, lokasi_opname,
                stok_sistem, stok_fisik, selisih, status_selisih, kondisi_fisik,
                petugas_opname, keterangan
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param(
            $stmt,
            'sisssiiissss',
            $kode_opname,
            $id_aset,
            $tanggal_opname,
            $periode_opname,
            $lokasi_opname,
            $stok_sistem,
            $stok_fisik,
            $selisih,
            $status_selisih,
            $kondisi_fisik,
            $petugas_opname,
            $keterangan
        );

        if (mysqli_stmt_execute($stmt)) {
            header("Location: stock_opname.php?msg=sukses_tambah");
            exit;
        } else {
            $error = "Gagal menyimpan data: " . mysqli_error($koneksi);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Stock Opname Baru | SIMARIS RS</title>
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
    </style>
</head>

<body>
    <div id="wrapper">
        <?php include(__DIR__ . '/../sidebar.php'); ?>

        <div id="page-content-wrapper">
            <div class="dashboard-header">
                <h3 class="mb-0 fw-bold"><i class="bi bi-clipboard-plus me-2"></i>Form Input Stock Opname Fisik</h3>
                <small class="text-white-50">Bandingkan kuantitas fisik riil di ruangan dengan saldo stok sistem</small>
            </div>

            <div class="content">
                <div class="row justify-content-center">
                    <div class="col-lg-9">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <div class="card shadow-sm border-0" style="border-radius: 12px; overflow: hidden;">
                            <div class="card-header card-header-theme py-3">
                                <i class="bi bi-pencil-square me-2"></i>Entri Data Hasil Pemeriksaan Lapangan
                            </div>
                            <div class="card-body p-4">
                                <form method="POST" id="formOpname">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">Kode Opname <span class="text-danger">*</span></label>
                                            <input type="text" name="kode_opname" class="form-control bg-light" value="<?= $nextCode ?>" readonly required>
                                            <small class="text-muted">Kode ter-generate otomatis</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">Periode Opname <span class="text-danger">*</span></label>
                                            <input type="text" name="periode_opname" class="form-control" value="Semester II <?= $thn_skrg ?>" required placeholder="Contoh: Semester II 2026 / Triwulan III">
                                        </div>

                                        <div class="col-md-12">
                                            <label class="form-label fw-semibold">Pilih Aset yang Diperiksa <span class="text-danger">*</span></label>
                                            <select name="id_aset" id="selectAset" class="form-select select2" required>
                                                <option value="">-- Pilih Aset Rumah Sakit --</option>
                                                <?php while ($a = mysqli_fetch_assoc($qAset)): ?>
                                                    <option value="<?= $a['id_aset'] ?>"
                                                        data-stok="<?= (int)$a['total_stok'] ?>"
                                                        data-lokasi="<?= htmlspecialchars($a['lokasi'] ?? '') ?>"
                                                        data-kategori="<?= htmlspecialchars($a['kategori_aset'] ?? '') ?>">
                                                        <?= htmlspecialchars($a['nama_aset']) ?> (Stok Sistem: <?= (int)$a['total_stok'] ?> | Lokasi: <?= htmlspecialchars($a['lokasi'] ?? '-') ?>)
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">Lokasi Pemeriksaan / Ruangan</label>
                                            <input type="text" name="lokasi_opname" id="inputLokasi" class="form-control" placeholder="Lokasi fisik saat diperiksa" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">Tanggal Pelaksanaan Opname <span class="text-danger">*</span></label>
                                            <input type="date" name="tanggal_opname" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                        </div>

                                        <!-- KOMPARASI SISTEM VS FISIK -->
                                        <div class="col-12 mt-4">
                                            <div class="p-3 bg-light rounded-3 border">
                                                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-calculator me-2"></i>Komparasi Kuantitas Stok</h6>
                                                <div class="row g-3 align-items-center">
                                                    <div class="col-md-4">
                                                        <label class="form-label small fw-semibold text-muted">Stok Tercatat di Sistem</label>
                                                        <input type="number" name="stok_sistem" id="stokSistem" class="form-control form-control-lg fw-bold text-primary bg-white" readonly value="0">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label small fw-semibold text-dark">Stok Fisik Nyata (Lapangan) <span class="text-danger">*</span></label>
                                                        <input type="number" name="stok_fisik" id="stokFisik" class="form-control form-control-lg fw-bold border-success" min="0" value="0" required>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label small fw-semibold text-muted">Selisih (Fisik - Sistem)</label>
                                                        <div id="boxSelisih" class="p-2 text-center rounded border fw-bold fs-5 bg-white text-success">
                                                            0 (Sesuai / Klop)
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6 mt-3">
                                            <label class="form-label fw-semibold">Kondisi Fisik Barang</label>
                                            <select name="kondisi_fisik" class="form-select">
                                                <option value="Baik">Baik</option>
                                                <option value="Rusak Ringan">Rusak Ringan</option>
                                                <option value="Rusak Berat">Rusak Berat</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mt-3">
                                            <label class="form-label fw-semibold">Nama Petugas Pemeriksa / Auditor <span class="text-danger">*</span></label>
                                            <input type="text" name="petugas_opname" class="form-control" value="<?= htmlspecialchars($_SESSION['nama_pengguna'] ?? 'Petugas') ?>" required>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label fw-semibold">Keterangan / Catatan Rekomendasi Tindak Lanjut</label>
                                            <textarea name="keterangan" rows="3" class="form-control" placeholder="Contoh: Kuantitas fisik cocok, 1 unit sedang pergantian baterai di bengkel IPSRS..."></textarea>
                                        </div>
                                    </div>

                                    <hr class="my-4">

                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="stock_opname.php" class="btn btn-secondary px-4">Batal</a>
                                        <button type="submit" name="simpan" class="btn btn-success px-4 fw-bold">
                                            <i class="bi bi-save me-1"></i> Simpan Hasil Opname
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const selectAset = document.getElementById('selectAset');
        const inputLokasi = document.getElementById('inputLokasi');
        const stokSistem = document.getElementById('stokSistem');
        const stokFisik = document.getElementById('stokFisik');
        const boxSelisih = document.getElementById('boxSelisih');

        selectAset.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            if (opt.value) {
                const stok = parseInt(opt.getAttribute('data-stok')) || 0;
                const lok = opt.getAttribute('data-lokasi') || '';
                stokSistem.value = stok;
                stokFisik.value = stok; // Default setara stok sistem
                inputLokasi.value = lok;
            } else {
                stokSistem.value = 0;
                stokFisik.value = 0;
                inputLokasi.value = '';
            }
            hitungSelisih();
        });

        stokFisik.addEventListener('input', hitungSelisih);

        function hitungSelisih() {
            const sistem = parseInt(stokSistem.value) || 0;
            const fisik = parseInt(stokFisik.value) || 0;
            const diff = fisik - sistem;

            if (diff === 0) {
                boxSelisih.className = 'p-2 text-center rounded border fw-bold fs-5 bg-success text-white';
                boxSelisih.innerHTML = '<i class="bi bi-check-circle me-1"></i> 0 (Sesuai / Klop)';
            } else if (diff < 0) {
                boxSelisih.className = 'p-2 text-center rounded border fw-bold fs-5 bg-danger text-white';
                boxSelisih.innerHTML = '<i class="bi bi-dash-circle me-1"></i> ' + diff + ' (Selisih Kurang)';
            } else {
                boxSelisih.className = 'p-2 text-center rounded border fw-bold fs-5 bg-warning text-dark';
                boxSelisih.innerHTML = '<i class="bi bi-plus-circle me-1"></i> +' + diff + ' (Selisih Lebih)';
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
