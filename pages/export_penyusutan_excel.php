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

$sql = "SELECT * FROM aset $whereSQL ORDER BY tanggal_masuk DESC, nama_aset ASC";
$res = mysqli_query($koneksi, $sql);

$filename = "Laporan_Penyusutan_Aset_Periode_" . $periode_tahun . "_" . date('Ymd_His') . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Export Penyusutan Aset</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 6px;
            vertical-align: middle;
        }

        th {
            background-color: #dbeafe;
            text-align: center;
            font-weight: bold;
        }

        .text-center {
            text-align: center;
        }

        .text-end {
            text-align: right;
        }

        .title {
            font-size: 14pt;
            font-weight: bold;
            text-align: center;
            margin-bottom: 4px;
        }

        .subtitle {
            font-size: 10pt;
            text-align: center;
            margin-bottom: 15px;
        }
    </style>
</head>

<body>
    <div class="title">RUMAH SAKIT BHAYANGKARA TK. III BANJARMASIN</div>
    <div class="title">LAPORAN REKAPITULASI PENYUSUTAN NILAI ASET (PERIODE TAHUN <?= $periode_tahun ?>)</div>
    <div class="subtitle">Metode: Garis Lurus (Straight-Line) | Tanggal Unduh: <?= date('d/m/Y H:i') ?> | Oleh: <?= htmlspecialchars($_SESSION['nama_pengguna'] ?? 'Petugas') ?></div>

    <table>
        <thead>
            <tr>
                <th rowspan="2">No</th>
                <th rowspan="2">Nama Aset</th>
                <th rowspan="2">Kategori</th>
                <th rowspan="2">Lokasi</th>
                <th rowspan="2">Tahun Masuk</th>
                <th rowspan="2">Umur (Thn)</th>
                <th rowspan="2">Qty</th>
                <th colspan="4">Data Keuangan & Depresiasi (Periode <?= $periode_tahun ?>)</th>
                <th rowspan="2">Status Manfaat</th>
            </tr>
            <tr>
                <th>Nilai Perolehan (Rp)</th>
                <th>Beban Susut Tahun <?= $periode_tahun ?> (Rp)</th>
                <th>Akumulasi Susut (Rp)</th>
                <th>Nilai Buku Akhir (Rp)</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $no = 1;
            $sum_h = 0;
            $sum_b = 0;
            $sum_a = 0;
            $sum_nb = 0;

            while ($r = mysqli_fetch_assoc($res)):
                $stok = (int)($r['total_stok'] ?? 0);
                $h_total = (float)$r['harga'] * $stok;
                $umur = (int)($r['umur_ekonomis'] ?? 0);
                $thn_masuk = (int)date('Y', strtotime($r['tanggal_masuk']));
                $kategori = $r['kategori_aset'] ?? '-';

                if ($periode_tahun < $thn_masuk) {
                    $beban = 0;
                    $akum = 0;
                    $nb = 0;
                    $status_str = 'Belum Diperoleh';
                } elseif ($umur > 0) {
                    $d_tahun = $h_total / $umur;
                    $thn_awal = max(0, min($umur, $periode_tahun - $thn_masuk));
                    $beban = ($thn_awal < $umur) ? $d_tahun : 0;
                    $thn_akhir = max(0, min($umur, $periode_tahun - $thn_masuk + 1));
                    $akum = $thn_akhir * $d_tahun;
                    $nb = max(0, $h_total - $akum);
                    $status_str = ($thn_akhir < $umur) ? "Aktif (Thn ke-" . ($thn_awal + 1) . "/$umur)" : "Habis Masa Manfaat";
                } else {
                    $beban = 0;
                    $akum = 0;
                    $nb = $h_total;
                    $status_str = 'Tanpa Susut';
                }

                $sum_h += $h_total;
                $sum_b += $beban;
                $sum_a += $akum;
                $sum_nb += $nb;
            ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td><?= htmlspecialchars($r['nama_aset']) ?></td>
                    <td class="text-center"><?= htmlspecialchars($kategori) ?></td>
                    <td><?= htmlspecialchars($r['lokasi'] ?? '-') ?></td>
                    <td class="text-center"><?= $thn_masuk ?></td>
                    <td class="text-center"><?= $umur > 0 ? $umur : '-' ?></td>
                    <td class="text-center"><?= $stok ?></td>
                    <td class="text-end"><?= number_format($h_total, 0, ',', '.') ?></td>
                    <td class="text-end"><?= number_format($beban, 0, ',', '.') ?></td>
                    <td class="text-end"><?= number_format($akum, 0, ',', '.') ?></td>
                    <td class="text-end"><?= number_format($nb, 0, ',', '.') ?></td>
                    <td class="text-center"><?= $status_str ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight: bold; background-color: #f1f5f9;">
                <td colspan="7" class="text-end">TOTAL (PERIODE TAHUN <?= $periode_tahun ?>):</td>
                <td class="text-end"><?= number_format($sum_h, 0, ',', '.') ?></td>
                <td class="text-end"><?= number_format($sum_b, 0, ',', '.') ?></td>
                <td class="text-end"><?= number_format($sum_a, 0, ',', '.') ?></td>
                <td class="text-end"><?= number_format($sum_nb, 0, ',', '.') ?></td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</body>

</html>
