<?php
session_start();
if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../login.php");
    exit;
}

include(__DIR__ . '/../config/koneksi.php');

// ==================== FILTER DATA ==================== //
$search   = isset($_GET['search']) ? mysqli_real_escape_string($koneksi, trim($_GET['search'])) : '';
$kategori = isset($_GET['kategori']) ? mysqli_real_escape_string($koneksi, $_GET['kategori']) : '';
$kondisi  = isset($_GET['kondisi']) ? mysqli_real_escape_string($koneksi, $_GET['kondisi']) : '';
$gedung   = isset($_GET['gedung']) ? mysqli_real_escape_string($koneksi, $_GET['gedung']) : '';

$where = [];
if ($search != '') {
    $where[] = "(kode_infrastruktur LIKE '%$search%' OR nama_infrastruktur LIKE '%$search%' OR fasilitas LIKE '%$search%' OR penanggung_jawab LIKE '%$search%')";
}
if ($kategori != '') $where[] = "kategori = '$kategori'";
if ($kondisi != '')  $where[] = "kondisi = '$kondisi'";
if ($gedung != '')   $where[] = "nama_gedung = '$gedung'";

$whereSQL = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT * FROM infrastruktur $whereSQL ORDER BY kategori ASC, id_infrastruktur DESC";
$res = mysqli_query($koneksi, $sql);

// Header Excel
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=Laporan_Infrastruktur_RS_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

echo chr(239) . chr(187) . chr(191); // UTF-8 BOM
?>
<table border="1">
    <thead>
        <tr style="background-color: #2c7a7b; color: #ffffff; font-weight: bold; text-align: center;">
            <th>No</th>
            <th>Kode Infrastruktur</th>
            <th>Nama Infrastruktur</th>
            <th>Kategori</th>
            <th>Nama Gedung</th>
            <th>Lantai</th>
            <th>Kapasitas / Bed</th>
            <th>Luas Area</th>
            <th>Fasilitas</th>
            <th>Kondisi Fisik</th>
            <th>Penanggung Jawab</th>
            <th>Keterangan</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $no = 1;
        while ($r = mysqli_fetch_assoc($res)):
        ?>
            <tr>
                <td align="center"><?= $no++; ?></td>
                <td align="center"><?= htmlspecialchars($r['kode_infrastruktur']); ?></td>
                <td><?= htmlspecialchars($r['nama_infrastruktur']); ?></td>
                <td align="center"><?= htmlspecialchars($r['kategori']); ?></td>
                <td><?= htmlspecialchars($r['nama_gedung']); ?></td>
                <td align="center"><?= htmlspecialchars($r['lantai']); ?></td>
                <td align="center"><?= htmlspecialchars($r['kapasitas'] ?? '-'); ?></td>
                <td align="center"><?= htmlspecialchars($r['luas_area'] ?? '-'); ?></td>
                <td><?= htmlspecialchars($r['fasilitas'] ?? '-'); ?></td>
                <td align="center"><?= htmlspecialchars($r['kondisi']); ?></td>
                <td><?= htmlspecialchars($r['penanggung_jawab'] ?? '-'); ?></td>
                <td><?= htmlspecialchars($r['keterangan'] ?? '-'); ?></td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>
