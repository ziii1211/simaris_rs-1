<?php
session_start();
if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../login.php");
    exit;
}

include(__DIR__ . '/../config/koneksi.php');

$tahun_aktif = date('Y');
$tahun_filter = isset($_GET['tahun']) && !empty($_GET['tahun']) ? (int)$_GET['tahun'] : (int)$tahun_aktif;
$kategori_filter = isset($_GET['kategori']) ? mysqli_real_escape_string($koneksi, $_GET['kategori']) : '';

$whereKat = !empty($kategori_filter) ? "WHERE kategori_aset = '$kategori_filter'" : "";

// Data Seluruh Aset
$qAset = mysqli_query($koneksi, "SELECT * FROM aset $whereKat ORDER BY lokasi ASC, nama_aset ASC");

$totalNilaiRS = 0;
$totalCapex = 0;
$listPrioritasGanti = [];

while ($a = mysqli_fetch_assoc($qAset)) {
    $thnMasuk = (int)date('Y', strtotime($a['tanggal_masuk']));
    $umurPakai = max(0, $tahun_filter - $thnMasuk);
    $umurEko = (int)$a['umur_ekonomis'];
    $sisaUmur = $umurEko - $umurPakai;
    $nilaiItem = (float)$a['harga'] * (int)$a['total_stok'];

    $totalNilaiRS += $nilaiItem;

    if ($sisaUmur <= 1 || (int)$a['stok_rusak'] > 0) {
        $totalCapex += $nilaiItem;
        $a['umur_pakai'] = $umurPakai;
        $a['sisa_umur'] = $sisaUmur;
        $a['nilai_total'] = $nilaiItem;
        $a['prioritas'] = ($sisaUmur <= 0 || $a['stok_rusak'] > 0) ? 'Prioritas 1 (Mendesak)' : 'Prioritas 2 (Siaga)';
        $listPrioritasGanti[] = $a;
    }
}

// Rekap Unit
$qUnit = mysqli_query($koneksi, "SELECT 
    lokasi,
    COUNT(*) as total_item,
    SUM(total_stok) as total_unit,
    SUM(stok_tersedia) as unit_baik,
    SUM(stok_rusak) as unit_rusak,
    SUM(stok_perawatan) as unit_rawat,
    SUM(harga * total_stok) as total_nilai
FROM aset 
$whereKat
GROUP BY lokasi 
ORDER BY total_nilai DESC");

// Total Biaya Pemeliharaan
$qK = mysqli_query($koneksi, "SELECT SUM(biaya_perbaikan) as tot_k FROM kerusakan");
$rK = mysqli_fetch_assoc($qK);
$totBiayaPerbaikan = (float)($rK['tot_k'] ?? 0);

$qP = mysqli_query($koneksi, "SELECT SUM(biaya_pemeliharaan) as tot_p FROM perawatan");
$rP = mysqli_fetch_assoc($qP);
$totBiayaPerawatan = (float)($rP['tot_p'] ?? 0);

$totBiayaMaint = $totBiayaPerbaikan + $totBiayaPerawatan;
$totalOpex = $totalNilaiRS * 0.05;

$filename = "Analisis_Perencanaan_Anggaran_Aset_" . $tahun_filter . "_" . date('Ymd_His') . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Analisis Aset & Anggaran</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10pt; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #333; padding: 6px; vertical-align: middle; }
        th { background-color: #dbeafe; font-weight: bold; text-align: center; }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }
        .title { font-size: 13pt; font-weight: bold; text-align: center; }
        .subtitle { font-size: 10pt; text-align: center; color: #555; margin-bottom: 15px; }
    </style>
</head>

<body>
    <div class="title">RUMAH SAKIT BHAYANGKARA TK. III BANJARMASIN</div>
    <div class="title" style="font-size: 11pt;">ANALISIS ASET & REKOMENDASI PERENCANAAN ANGGARAN (DSS)</div>
    <div class="subtitle">Proyeksi Kebutuhan Belanja Modal (CAPEX) & Pemeliharaan (OPEX) Tahun <?= $tahun_filter ?></div>

    <!-- Ringkasan Eksekutif -->
    <table>
        <tr>
            <th colspan="4" style="background-color: #1e3a8a; color: #fff; font-size: 11pt;">RINGKASAN EKSEKUTIF ALOKASI ANGGARAN TAHUN <?= $tahun_filter ?></th>
        </tr>
        <tr>
            <th>Nilai Investasi Aset Aktif</th>
            <th>Total Biaya Pemeliharaan Historis</th>
            <th>Usulan Belanja Modal (CAPEX)</th>
            <th>Estimasi Operasional Pemeliharaan (OPEX 5%)</th>
        </tr>
        <tr>
            <td class="text-center fw-bold">Rp <?= number_format($totalNilaiRS, 0, ',', '.') ?></td>
            <td class="text-center fw-bold">Rp <?= number_format($totBiayaMaint, 0, ',', '.') ?></td>
            <td class="text-center fw-bold" style="color: #dc2626;">Rp <?= number_format($totalCapex, 0, ',', '.') ?></td>
            <td class="text-center fw-bold" style="color: #d97706;">Rp <?= number_format($totalOpex, 0, ',', '.') ?></td>
        </tr>
    </table>

    <!-- Tabel Prioritas Penggantian -->
    <div class="fw-bold" style="margin-bottom: 5px;">1. DAFTAR ASET PRIORITAS PENGGANTIAN (END-OF-LIFE & RUSAK)</div>
    <table>
        <thead>
            <tr style="background-color: #fee2e2;">
                <th style="width: 40px;">No</th>
                <th>Kode Aset</th>
                <th>Nama Barang / Alkes</th>
                <th>Lokasi Ruangan</th>
                <th>Tahun Pengadaan</th>
                <th>Umur Pakai</th>
                <th>Umur Ekonomis</th>
                <th>Sisa Umur</th>
                <th>Harga Satuan</th>
                <th>Total Kebutuhan CAPEX</th>
                <th>Prioritas Penggantian</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($listPrioritasGanti)): ?>
                <tr><td colspan="11" class="text-center">Tidak ada aset kritis/habis umur ekonomis.</td></tr>
            <?php else: ?>
                <?php foreach ($listPrioritasGanti as $idx => $lg): ?>
                    <tr>
                        <td class="text-center"><?= $idx + 1 ?></td>
                        <td class="text-center fw-bold"><?= htmlspecialchars($lg['kode_aset']) ?></td>
                        <td><?= htmlspecialchars($lg['nama_aset']) ?></td>
                        <td><?= htmlspecialchars($lg['lokasi']) ?></td>
                        <td class="text-center"><?= date('Y', strtotime($lg['tanggal_masuk'])) ?></td>
                        <td class="text-center"><?= $lg['umur_pakai'] ?> Thn</td>
                        <td class="text-center"><?= $lg['umur_ekonomis'] ?> Thn</td>
                        <td class="text-center fw-bold" style="color: <?= $lg['sisa_umur'] <= 0 ? 'red' : 'orange' ?>;"><?= $lg['sisa_umur'] ?> Thn</td>
                        <td class="text-end">Rp <?= number_format($lg['harga'], 0, ',', '.') ?></td>
                        <td class="text-end fw-bold">Rp <?= number_format($lg['nilai_total'], 0, ',', '.') ?></td>
                        <td class="text-center fw-bold"><?= $lg['prioritas'] ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr style="background-color: #f1f5f9;">
                    <td colspan="9" class="text-end fw-bold">TOTAL USULAN KEBUTUHAN BELANJA MODAL (CAPEX)</td>
                    <td class="text-end fw-bold" style="color: #dc2626;">Rp <?= number_format($totalCapex, 0, ',', '.') ?></td>
                    <td></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Tabel Rekapitulasi per Unit -->
    <div class="fw-bold" style="margin-bottom: 5px;">2. SEBARAN NILAI & KONDISI FISIK PER UNIT PELAYANAN</div>
    <table>
        <thead>
            <tr style="background-color: #e0f2fe;">
                <th style="width: 40px;">No</th>
                <th>Nama Ruangan / Unit Pelayanan</th>
                <th>Jml Item</th>
                <th>Total Unit Fisik</th>
                <th>Kondisi Baik</th>
                <th>Kondisi Rusak</th>
                <th>Kondisi Perawatan</th>
                <th>Indeks Keandalan (%)</th>
                <th>Total Nilai Aset</th>
            </tr>
        </thead>
        <tbody>
            <?php $noU = 1; while ($ru = mysqli_fetch_assoc($qUnit)): ?>
                <?php
                $totU = (int)$ru['total_unit'];
                $baikU = (int)$ru['unit_baik'];
                $keandalan = $totU > 0 ? round(($baikU / $totU) * 100, 1) : 0;
                ?>
                <tr>
                    <td class="text-center"><?= $noU++ ?></td>
                    <td><?= htmlspecialchars($ru['lokasi']) ?></td>
                    <td class="text-center"><?= $ru['total_item'] ?> Item</td>
                    <td class="text-center fw-bold"><?= $totU ?></td>
                    <td class="text-center"><?= $baikU ?></td>
                    <td class="text-center" style="color: red;"><?= $ru['unit_rusak'] ?></td>
                    <td class="text-center" style="color: orange;"><?= $ru['unit_rawat'] ?></td>
                    <td class="text-center fw-bold"><?= $keandalan ?>%</td>
                    <td class="text-end fw-bold">Rp <?= number_format($ru['total_nilai'], 0, ',', '.') ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <!-- Tanda Tangan -->
    <br>
    <table style="border: none; width: 100%;">
        <tr style="border: none;">
            <td style="border: none; width: 50%; text-align: center;">
                Mengetahui,<br>
                <strong>Penanggung Jawab Sarana & Prasarana</strong><br><br><br><br>
                <u><?= htmlspecialchars($_SESSION['nama_pengguna'] ?? 'Petugas Sarpras') ?></u><br>
                NIP. <?= htmlspecialchars($_SESSION['nip'] ?? '........................................') ?>
            </td>
            <td style="border: none; width: 50%; text-align: center;">
                Banjarmasin, <?= date('d F Y') ?><br>
                <strong>Kepala Rumah Sakit Bhayangkara Tk. III Banjarmasin</strong><br><br><br><br>
                <u>dr. Bambang Pitoyo, Sp.S., M.H.</u><br>
                NIP/NRP. 197005151998031002
            </td>
        </tr>
    </table>
</body>

</html>
