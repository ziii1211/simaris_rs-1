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

$sql = "
    SELECT so.*, a.nama_aset, a.kategori_aset
    FROM stock_opname so
    JOIN aset a ON so.id_aset = a.id_aset
    $whereSQL
    ORDER BY so.tanggal_opname DESC, so.id_opname DESC
";
$res = mysqli_query($koneksi, $sql);

$filename = "Laporan_Hasil_Stock_Opname_" . date('Ymd_His') . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Export Stock Opname</title>
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
    <div class="title">LAPORAN HASIL STOCK OPNAME ASET (SELISIH FISIK VS SISTEM)</div>
    <div class="subtitle">Tanggal Unduh: <?= date('d/m/Y H:i') ?> | Dicetak Oleh: <?= htmlspecialchars($_SESSION['nama_pengguna'] ?? 'Petugas') ?></div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Kode Opname</th>
                <th>Nama Aset</th>
                <th>Kategori</th>
                <th>Tanggal</th>
                <th>Periode</th>
                <th>Lokasi Cek</th>
                <th>Stok Sistem</th>
                <th>Stok Fisik</th>
                <th>Selisih</th>
                <th>Status Selisih</th>
                <th>Kondisi Fisik</th>
                <th>Pemeriksa</th>
                <th>Keterangan / Rekomendasi</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $no = 1;
            $tot_s = 0;
            $tot_f = 0;
            $tot_diff = 0;

            while ($r = mysqli_fetch_assoc($res)):
                $selisih = (int)$r['selisih'];
                $tot_s += (int)$r['stok_sistem'];
                $tot_f += (int)$r['stok_fisik'];
                $tot_diff += $selisih;
                $diff_str = ($selisih > 0) ? '+' . $selisih : (string)$selisih;
            ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td class="text-center"><?= htmlspecialchars($r['kode_opname']) ?></td>
                    <td><?= htmlspecialchars($r['nama_aset']) ?></td>
                    <td class="text-center"><?= htmlspecialchars($r['kategori_aset'] ?? '-') ?></td>
                    <td class="text-center"><?= date('d/m/Y', strtotime($r['tanggal_opname'])) ?></td>
                    <td class="text-center"><?= htmlspecialchars($r['periode_opname']) ?></td>
                    <td><?= htmlspecialchars($r['lokasi_opname']) ?></td>
                    <td class="text-center"><?= $r['stok_sistem'] ?></td>
                    <td class="text-center"><?= $r['stok_fisik'] ?></td>
                    <td class="text-center"><?= $diff_str ?></td>
                    <td class="text-center"><?= htmlspecialchars($r['status_selisih']) ?></td>
                    <td class="text-center"><?= htmlspecialchars($r['kondisi_fisik']) ?></td>
                    <td><?= htmlspecialchars($r['petugas_opname']) ?></td>
                    <td><?= htmlspecialchars($r['keterangan'] ?? '-') ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight: bold; background-color: #f1f5f9;">
                <td colspan="7" class="text-end">TOTAL KESELURUHAN:</td>
                <td class="text-center"><?= $tot_s ?></td>
                <td class="text-center"><?= $tot_f ?></td>
                <td class="text-center"><?= ($tot_diff > 0 ? '+' : '') . $tot_diff ?></td>
                <td colspan="4"></td>
            </tr>
        </tfoot>
    </table>
</body>

</html>
