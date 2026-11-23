<?php
session_start();

if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../login.php");
    exit;
}

require(__DIR__ . '/../config/fpdf.php');
include(__DIR__ . '/../config/koneksi.php');
require_once(__DIR__ . '/../config/ttd_helper.php');

// ================= FILTER DATA ================= //
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

// ================= SETUP PDF ================= //
$pdf = new FPDF('L', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetMargins(10, 10, 10);

// Logo kiri & kanan
renderOfficialKopSurat($pdf, 'L');

// Judul Laporan
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 8, 'LAPORAN REKAPITULASI INFRASTRUKTUR GEDUNG & KAMAR', 0, 1, 'C');

// Sub Filter Info
$subInfo = [];
if (!empty($kategori)) $subInfo[] = "Kategori: " . $kategori;
if (!empty($gedung))   $subInfo[] = "Gedung: " . $gedung;
if (!empty($kondisi))  $subInfo[] = "Kondisi: " . $kondisi;

if (count($subInfo)) {
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->Cell(0, 5, implode(' | ', $subInfo), 0, 1, 'C');
}
$pdf->Ln(4);

// ================= HEADER TABEL ================= //
$widths = [10, 26, 60, 35, 45, 30, 22, 25, 24];
$headers = ['No', 'Kode', 'Nama Infrastruktur', 'Kategori', 'Lokasi Gedung / Lt', 'Kapasitas', 'Luas', 'Kondisi', 'P. Jawab'];

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(72, 201, 176);
$pdf->SetTextColor(255);

foreach ($headers as $i => $h) {
    $pdf->Cell($widths[$i], 9, $h, 1, 0, 'C', true);
}
$pdf->Ln();

// ================= ISI TABEL ================= //
$pdf->SetFont('Arial', '', 8.5);
$pdf->SetTextColor(0);
$no = 1;

if (mysqli_num_rows($res) == 0) {
    $pdf->Cell(array_sum($widths), 10, 'Tidak ada data infrastruktur yang ditemukan.', 1, 1, 'C');
} else {
    while ($r = mysqli_fetch_assoc($res)) {
        if ($pdf->GetY() > 175) {
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(72, 201, 176);
            $pdf->SetTextColor(255);
            foreach ($headers as $i => $h) {
                $pdf->Cell($widths[$i], 9, $h, 1, 0, 'C', true);
            }
            $pdf->Ln();
            $pdf->SetFont('Arial', '', 8.5);
            $pdf->SetTextColor(0);
        }

        $loc = $r['nama_gedung'] . ' (' . $r['lantai'] . ')';

        $pdf->Cell($widths[0], 8, $no++, 1, 0, 'C');
        $pdf->Cell($widths[1], 8, $r['kode_infrastruktur'], 1, 0, 'C');
        $pdf->Cell($widths[2], 8, substr($r['nama_infrastruktur'], 0, 36), 1, 0, 'L');
        $pdf->Cell($widths[3], 8, substr($r['kategori'], 0, 22), 1, 0, 'C');
        $pdf->Cell($widths[4], 8, substr($loc, 0, 28), 1, 0, 'L');
        $pdf->Cell($widths[5], 8, substr($r['kapasitas'] ?? '-', 0, 18), 1, 0, 'C');
        $pdf->Cell($widths[6], 8, substr($r['luas_area'] ?? '-', 0, 12), 1, 0, 'C');
        $pdf->Cell($widths[7], 8, $r['kondisi'], 1, 0, 'C');
        $pdf->Cell($widths[8], 8, substr($r['penanggung_jawab'] ?? '-', 0, 14), 1, 1, 'L');
    }
}

// ================= TANDA TANGAN (2 KOLOM RESMI) ================= //
renderReportSignature($pdf, $koneksi);

// ================= OUTPUT PDF ================= //
ob_clean();
$pdf->Output('I', 'Laporan_Rekapitulasi_Infrastruktur_' . date('Ymd_His') . '.pdf');
exit;
