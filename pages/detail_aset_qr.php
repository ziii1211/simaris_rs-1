<?php
session_start();
include(__DIR__ . '/../config/koneksi.php');

$isLoggedIn = isset($_SESSION['id_pengguna']);
$currentUser = $_SESSION['nama_pengguna'] ?? 'Petugas';
$userRole = $_SESSION['level'] ?? $_SESSION['role'] ?? 'guest';

$rawCode = trim($_GET['kode'] ?? $_GET['code'] ?? $_GET['id'] ?? '');

if (empty($rawCode)) {
    die("Kode atau ID aset tidak disertakan.");
}

$safeCode = mysqli_real_escape_string($koneksi, $rawCode);
$query = mysqli_query($koneksi, "SELECT * FROM aset WHERE kode_aset = '$safeCode' OR id_aset = '$safeCode' LIMIT 1");
$aset = mysqli_fetch_assoc($query);

if (!$aset) {
    die("Aset dengan kode '$safeCode' tidak ditemukan dalam sistem SIMARIS RS.");
}

$id_aset = (int)$aset['id_aset'];
$kode_aset = !empty($aset['kode_aset']) ? $aset['kode_aset'] : sprintf("RSB-%03d", $aset['id_aset']);

// Handle mutasi lokasi jika form disubmit dari halaman ini
$msg = "";
$msgType = "";
if ($isLoggedIn && isset($_POST['proses_mutasi'])) {
    $lokasi_baru = trim($_POST['lokasi_baru'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? 'Mutasi via QR Scan Mobile');
    $penanggung_jawab = trim($_POST['penanggung_jawab'] ?? $currentUser);
    $lokasi_lama = $aset['lokasi'];

    if (empty($lokasi_baru)) {
        $msg = "Silakan pilih ruangan tujuan baru.";
        $msgType = "danger";
    } elseif ($lokasi_lama === $lokasi_baru) {
        $msg = "Aset sudah berada di ruangan tersebut.";
        $msgType = "warning";
    } else {
        $sLokasiBaru = mysqli_real_escape_string($koneksi, $lokasi_baru);
        $sLokasiLama = mysqli_real_escape_string($koneksi, $lokasi_lama);
        $sKet = mysqli_real_escape_string($koneksi, $keterangan);
        $sPj = mysqli_real_escape_string($koneksi, $penanggung_jawab);
        $tglNow = date('Y-m-d H:i:s');

        $inRiwayat = mysqli_query($koneksi, "INSERT INTO riwayat_lokasi (id_aset, lokasi_sebelumnya, lokasi_baru, tanggal_pindah, keterangan, penanggung_jawab) 
            VALUES ($id_aset, '$sLokasiLama', '$sLokasiBaru', '$tglNow', '$sKet', '$sPj')");
        $upAset = mysqli_query($koneksi, "UPDATE aset SET lokasi = '$sLokasiBaru' WHERE id_aset = $id_aset");

        if ($upAset) {
            $msg = "Aset berhasil dipindahkan ke <strong>" . htmlspecialchars($lokasi_baru) . "</strong>!";
            $msgType = "success";
            $aset['lokasi'] = $lokasi_baru; // Refresh
        } else {
            $msg = "Gagal memindahkan aset: " . mysqli_error($koneksi);
            $msgType = "danger";
        }
    }
}

// Ambil riwayat mutasi
$qRiwayat = mysqli_query($koneksi, "SELECT * FROM riwayat_lokasi WHERE id_aset = $id_aset ORDER BY id_riwayat DESC");

// Ambil daftar seluruh ruangan untuk dropdown mutasi
$qRuangan = mysqli_query($koneksi, "SELECT DISTINCT lokasi FROM aset WHERE lokasi IS NOT NULL AND lokasi != '' ORDER BY lokasi ASC");
$listRuangan = [];
while ($r = mysqli_fetch_assoc($qRuangan)) {
    $listRuangan[] = $r['lokasi'];
}

// URL QR Code
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
$currentUrl = $protocol . "://" . $host . $_SERVER['REQUEST_URI'];
$qrImgUrl = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=" . urlencode($currentUrl);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Aset: <?= htmlspecialchars($kode_aset) ?> | SIMARIS RS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto+Mono:wght@600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f1f5f9;
            color: #1e293b;
            padding-bottom: 50px;
        }

        .header-top {
            background: linear-gradient(135deg, #1e3a8a, #0d9488);
            color: #fff;
            padding: 24px 20px;
            border-radius: 0 0 24px 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .card-custom {
            background: #fff;
            border-radius: 16px;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            margin-top: -30px;
            overflow: hidden;
        }

        .badge-code {
            font-family: 'Roboto Mono', monospace;
            font-size: 1.1rem;
            background: #0f172a;
            color: #38bdf8;
            padding: 6px 14px;
            border-radius: 8px;
            display: inline-block;
        }

        .qr-card-box {
            background: #f8fafc;
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 15px;
            text-align: center;
        }

        .info-row {
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 500;
        }

        .info-value {
            font-size: 0.95rem;
            color: #0f172a;
            font-weight: 600;
        }

        .timeline-item {
            position: relative;
            padding-left: 28px;
            margin-bottom: 18px;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 6px;
            bottom: -18px;
            width: 2px;
            background: #e2e8f0;
        }

        .timeline-item:last-child::before {
            display: none;
        }

        .timeline-dot {
            position: absolute;
            left: 0;
            top: 4px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #0d9488;
            border: 3px solid #ccfbf1;
        }
    </style>
</head>

<body>
    <!-- Header Top -->
    <div class="header-top">
        <div class="container" style="max-width: 760px;">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="d-flex align-items-center gap-2">
                    <img src="../assets/img/logo_rs.jpg" alt="Logo RS" style="height: 38px; border-radius: 50%; background: #fff; padding: 2px;" onerror="this.src='../assets/img/logo.png'">
                    <div>
                        <div class="fw-bold" style="font-size: 0.95rem; letter-spacing: 0.5px;">RS BHAYANGKARA TK. III BANJARMASIN</div>
                        <div class="small text-white-50">Sistem Informasi Manajemen Aset & Infrastruktur (SIMARIS)</div>
                    </div>
                </div>
                <?php if ($isLoggedIn): ?>
                    <a href="aset.php" class="btn btn-sm btn-light text-dark fw-bold rounded-pill px-3">
                        <i class="bi bi-arrow-left"></i> Kembali
                    </a>
                <?php else: ?>
                    <a href="../login.php" class="btn btn-sm btn-light text-dark fw-bold rounded-pill px-3">
                        <i class="bi bi-box-arrow-in-right"></i> Login
                    </a>
                <?php endif; ?>
            </div>
            <div class="text-center py-2">
                <span class="badge bg-success bg-opacity-25 border border-success text-white px-3 py-2 rounded-pill small">
                    <i class="bi bi-shield-check me-1"></i> ASET RESMI TERVERIFIKASI SISTEM
                </span>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="container" style="max-width: 760px;">
        <?php if (!empty($msg)): ?>
            <div class="alert alert-<?= $msgType ?> alert-dismissible fade show mt-3" role="alert">
                <?= $msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card card-custom p-4 mt-3">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 pb-3 border-bottom">
                <div>
                    <span class="badge-code"><?= htmlspecialchars($kode_aset) ?></span>
                    <h3 class="fw-bold text-dark mt-2 mb-1"><?= htmlspecialchars($aset['nama_aset']) ?></h3>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge <?= $aset['kategori_aset'] == 'Medis' ? 'bg-danger' : 'bg-primary' ?> px-2 py-1">
                            <?= htmlspecialchars($aset['kategori_aset']) ?>
                        </span>
                        <span class="text-muted small">Jenis: <?= htmlspecialchars($aset['jenis']) ?> | Tipe: <?= htmlspecialchars($aset['tipe_aset']) ?></span>
                    </div>
                </div>
                <div class="text-md-end d-flex gap-2 justify-content-md-end flex-wrap">
                    <a href="logbook_aset.php?id=<?= $aset['id_aset'] ?>" class="btn btn-primary btn-sm rounded-pill px-3 fw-bold shadow-sm">
                        <i class="bi bi-journal-medical me-1"></i> Buku Riwayat & Monitoring
                    </a>
                    <a href="cetak_label_qr.php?id=<?= $aset['id_aset'] ?>" target="_blank" class="btn btn-outline-dark btn-sm rounded-pill px-3">
                        <i class="bi bi-printer me-1"></i> Cetak Label
                    </a>
                </div>
            </div>

            <!-- Lokasi Terkini Highlight Card -->
            <div class="p-3 my-3 rounded-4" style="background: linear-gradient(135deg, #e0f2fe, #dcfce7); border-left: 5px solid #0284c7;">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <div class="text-secondary small fw-bold text-uppercase">Posisi Ruangan Saat Ini</div>
                        <h4 class="fw-bold text-dark m-0">
                            <i class="bi bi-geo-alt-fill text-danger me-1"></i> <?= htmlspecialchars($aset['lokasi']) ?>
                        </h4>
                        <?php if (!empty($aset['pj_ruangan'])): ?>
                            <div class="small text-muted mt-1"><i class="bi bi-person-badge text-primary me-1"></i> PJ Ruangan: <strong><?= htmlspecialchars($aset['pj_ruangan']) ?></strong></div>
                        <?php endif; ?>
                    </div>
                    <?php if ($isLoggedIn): ?>
                        <button class="btn btn-primary btn-sm rounded-pill px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#modalMutasi">
                            <i class="bi bi-arrow-left-right me-1"></i> Pindahkan Ruangan
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row g-4 mt-1">
                <!-- QR Code View -->
                <div class="col-md-4 text-center">
                    <div class="qr-card-box">
                        <img src="<?= $qrImgUrl ?>" alt="QR Code" class="img-fluid rounded mb-2" style="max-width: 180px;">
                        <div class="text-muted small fw-bold">QR Code Fisik Aset</div>
                        <div class="text-secondary" style="font-size: 0.75rem;">Scan kapan saja untuk validasi & pelacakan</div>
                        <div class="mt-2">
                            <a href="logbook_aset.php?id=<?= $aset['id_aset'] ?>" class="btn btn-sm btn-outline-success w-100 rounded-pill">
                                <i class="bi bi-activity me-1"></i> Lihat Kartu Pemeliharaan
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Spesifikasi Teknis & Status Stok -->
                <div class="col-md-8">
                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-card-checklist text-primary"></i> Rincian Fisik, Kondisi & Pemeliharaan</h6>
                    <div class="info-row d-flex justify-content-between">
                        <span class="info-label">Tingkat Kepentingan / Prioritas</span>
                        <span class="info-value">
                            <?php if (($aset['tingkat_prioritas'] ?? '') == 'Kritis'): ?>
                                <span class="badge bg-danger"><i class="bi bi-exclamation-octagon-fill me-1"></i> Prioritas 1 - Kritis (Live Saving)</span>
                            <?php elseif (($aset['tingkat_prioritas'] ?? '') == 'Penting'): ?>
                                <span class="badge bg-warning text-dark"><i class="bi bi-shield-fill-exclamation me-1"></i> Prioritas 2 - Penting</span>
                            <?php else: ?>
                                <span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i> Prioritas 3 - Rutin</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="info-row d-flex justify-content-between">
                        <span class="info-label">Kondisi Fisik Terkini</span>
                        <span class="info-value">
                            <?php
                            $kondisiAset = $aset['kondisi'] ?? 'Baik';
                            $badgeKondisi = ($kondisiAset == 'Baik') ? 'bg-success' : (($kondisiAset == 'Rusak Berat') ? 'bg-danger' : 'bg-warning text-dark');
                            ?>
                            <span class="badge <?= $badgeKondisi ?> px-2 py-1"><?= htmlspecialchars($kondisiAset) ?></span>
                        </span>
                    </div>
                    <div class="info-row d-flex justify-content-between">
                        <span class="info-label">Jadwal Maintenance / Kalibrasi</span>
                        <span class="info-value">
                            <?php if (!empty($aset['jadwal_maintenance_berikutnya'])): 
                                $tglM = $aset['jadwal_maintenance_berikutnya'];
                                $diffD = round((strtotime($tglM) - time()) / 86400);
                            ?>
                                <span class="badge <?= $diffD < 0 ? 'bg-danger' : ($diffD <= 14 ? 'bg-warning text-dark' : 'bg-info text-dark') ?>">
                                    <?= date('d M Y', strtotime($tglM)) ?> (<?= $diffD < 0 ? 'Lewat ' . abs($diffD) . ' hari' : $diffD . ' hari lagi' ?>)
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">Belum dijadwalkan</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="info-row d-flex justify-content-between">
                        <span class="info-label">Total Stok Unit</span>
                        <span class="info-value text-primary fs-6"><?= $aset['total_stok'] ?? 1 ?> Unit</span>
                    </div>
                    <div class="info-row d-flex justify-content-between">
                        <span class="info-label">Rincian Stok</span>
                        <span class="info-value">
                            <span class="badge bg-success">Tersedia: <?= $aset['stok_tersedia'] ?? 0 ?></span>
                            <span class="badge bg-danger">Rusak: <?= $aset['stok_rusak'] ?? 0 ?></span>
                            <span class="badge bg-warning text-dark">Perawatan: <?= $aset['stok_perawatan'] ?? 0 ?></span>
                        </span>
                    </div>
                    <div class="info-row d-flex justify-content-between">
                        <span class="info-label">Asal Usul Pengadaan</span>
                        <span class="info-value"><?= htmlspecialchars($aset['asal_usul']) ?></span>
                    </div>
                    <div class="info-row d-flex justify-content-between">
                        <span class="info-label">Harga Perolehan</span>
                        <span class="info-value text-success">Rp <?= number_format($aset['harga'], 0, ',', '.') ?></span>
                    </div>
                    <div class="info-row d-flex justify-content-between">
                        <span class="info-label">Umur Ekonomis</span>
                        <span class="info-value"><?= $aset['umur_ekonomis'] ?> Tahun</span>
                    </div>
                    <div class="info-row d-flex justify-content-between">
                        <span class="info-label">Tanggal Masuk</span>
                        <span class="info-value"><?= date('d-m-Y', strtotime($aset['tanggal_masuk'])) ?></span>
                    </div>
                </div>
            </div>

            <!-- Riwayat Perpindahan Ruangan (Audit Trail) -->
            <div class="mt-4 pt-3 border-top">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-dark m-0"><i class="bi bi-clock-history text-success"></i> Riwayat Pelacakan & Mutasi Ruangan</h6>
                    <span class="badge bg-light text-secondary border"><?= mysqli_num_rows($qRiwayat) ?> Catatan</span>
                </div>

                <?php if (mysqli_num_rows($qRiwayat) == 0): ?>
                    <div class="alert alert-light text-secondary small text-center py-3 border">
                        <i class="bi bi-info-circle me-1"></i> Aset ini belum pernah dipindahkan sejak ditempatkan di <strong><?= htmlspecialchars($aset['lokasi']) ?></strong>.
                    </div>
                <?php else: ?>
                    <div class="ps-2 mt-3">
                        <?php while ($h = mysqli_fetch_assoc($qRiwayat)): ?>
                            <div class="timeline-item">
                                <div class="timeline-dot"></div>
                                <div class="fw-bold text-dark" style="font-size: 0.9rem;">
                                    Dipindahkan ke: <span class="text-primary"><?= htmlspecialchars($h['lokasi_baru']) ?></span>
                                </div>
                                <div class="text-muted small">
                                    Dari: <em><?= htmlspecialchars($h['lokasi_sebelumnya'] ?? '-') ?></em> |
                                    Tgl: <?= date('d M Y, H:i', strtotime($h['tanggal_pindah'])) ?>
                                </div>
                                <?php if (!empty($h['penanggung_jawab'])): ?>
                                    <div class="small text-secondary">
                                        <i class="bi bi-person me-1"></i> PJ: <?= htmlspecialchars($h['penanggung_jawab']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($h['keterangan'])): ?>
                                    <div class="small text-secondary fst-italic">
                                        Ket: <?= htmlspecialchars($h['keterangan']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Mutasi Lokasi -->
    <?php if ($isLoggedIn): ?>
        <div class="modal fade" id="modalMutasi" tabindex="-1" aria-labelledby="modalMutasiLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <form method="POST" action="">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title fs-6 fw-bold" id="modalMutasiLabel">
                                <i class="bi bi-arrow-left-right me-1"></i> Mutasi / Pindah Ruangan Aset
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-secondary">Aset</label>
                                <input type="text" class="form-control form-control-sm bg-light" value="<?= htmlspecialchars($aset['nama_aset']) ?> (<?= htmlspecialchars($kode_aset) ?>)" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-secondary">Ruangan Saat Ini</label>
                                <input type="text" class="form-control form-control-sm bg-light text-danger fw-bold" value="<?= htmlspecialchars($aset['lokasi']) ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Pilih Ruangan Tujuan Baru <span class="text-danger">*</span></label>
                                <select name="lokasi_baru" class="form-select form-select-sm" required>
                                    <option value="">-- Pilih Ruangan Baru --</option>
                                    <?php foreach ($listRuangan as $r): ?>
                                        <option value="<?= htmlspecialchars($r) ?>" <?= $r == $aset['lokasi'] ? 'disabled' : '' ?>>
                                            <?= htmlspecialchars($r) ?> <?= $r == $aset['lokasi'] ? '(Lokasi Sekarang)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Penanggung Jawab Mutasi</label>
                                <input type="text" name="penanggung_jawab" class="form-control form-control-sm" value="<?= htmlspecialchars($currentUser) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Alasan / Keterangan Perpindahan</label>
                                <textarea name="keterangan" class="form-control form-control-sm" rows="2" placeholder="Contoh: Kebutuhan operasional darurat di IGD..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer bg-light">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" name="proses_mutasi" class="btn btn-primary btn-sm px-3 fw-bold">
                                <i class="bi bi-check-lg me-1"></i> Simpan Perpindahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
