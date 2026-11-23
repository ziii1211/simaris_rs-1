<?php
session_start();
if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../login.php");
    exit;
}

ob_start(); // Ditambahkan biar aman dari output whitespace seperti di laporan audit
require(__DIR__ . '/../config/fpdf.php');
include(__DIR__ . '/../config/koneksi.php');
require_once(__DIR__ . '/../config/ttd_helper.php');

// ==================== FILTER DATA ==================== //
$search  = mysqli_real_escape_string($koneksi, $_GET['search'] ?? '');
$kat     = mysqli_real_escape_string($koneksi, $_GET['kategori'] ?? '');

$where = ["(kerusakan.status = 'Dalam Perbaikan' OR kerusakan.status = 'Selesai Diperbaiki')"];
if ($search != '')  $where[] = "(kerusakan.nama_aset LIKE '%$search%' OR kerusakan.keterangan LIKE '%$search%' OR kerusakan.teknisi LIKE '%$search%' OR kerusakan.pelapor LIKE '%$search%' OR aset.lokasi LIKE '%$search%')";
if ($kat != '')     $where[] = "aset.kategori_aset = '$kat'";

$whereSQL = implode(" AND ", $where);

// ==================== QUERY ==================== //
$sql = "SELECT kerusakan.*, aset.kategori_aset, aset.lokasi 
        FROM kerusakan 
        LEFT JOIN aset ON kerusakan.nama_aset = aset.nama_aset COLLATE utf8mb4_general_ci
        WHERE $whereSQL ORDER BY kerusakan.tanggal DESC";
$res = mysqli_query($koneksi, $sql);

// ==================== PDF SETUP ==================== //
// Inisialisasi FPDF (Sebelumnya ini terlewat makanya logo tidak jalan)
$pdf = new FPDF('L', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(false);

renderOfficialKopSurat($pdf, 'L');

$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'LAPORAN DATA PERBAIKAN ASET', 0, 1, 'C');
$pdf->Ln(5);

// ==================== HEADER TABEL (9 Kolom) ==================== //
// Total lebar tetap 277mm untuk kertas A4 Landscape
$w = [10, 35, 25, 20, 25, 25, 20, 25, 92];
$header = ['No', 'Nama Aset', 'Lokasi', 'Kategori', 'Pelapor', 'Teknisi', 'Tanggal', 'Status', 'Keterangan'];

function cetakHeaderPerbaikan($pdf, $w, $header)
{
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(72, 201, 176);
    $pdf->SetTextColor(255);
    foreach ($header as $i => $h) {
        $pdf->Cell($w[$i], 9, $h, 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0);
}

cetakHeaderPerbaikan($pdf, $w, $header);

// ==================== ISI DATA ==================== //
// ==================== ISI DATA ==================== //
$no = 1;
while ($r = mysqli_fetch_assoc($res)) {

    $nama_aset  = $r['nama_aset'] ?? '-';
    $lokasi     = $r['lokasi'] ?? '-';
    $kategori   = $r['kategori_aset'] ?? '-';
    $pelapor    = $r['pelapor'] ?? '-';
    $teknisi    = $r['teknisi'] ?? '-';
    $tanggal    = date('d-m-Y', strtotime($r['tanggal']));
    $status     = $r['status'] ?? '-';
    $keterangan = $r['keterangan'] ?? '-';

    // Membatasi teks nama aset agar tidak terlalu panjang (opsional)
    if (strlen($nama_aset) > 20) $nama_aset = substr($nama_aset, 0, 18) . '...';

    // PERBAIKAN: Menghitung maksimal baris dengan mempertimbangkan semua kolom yang berpotensi multiline
    $maxLine = max(
        1,
        ceil(strlen($nama_aset) / 20),
        ceil(strlen($lokasi) / 15),      // Kolom lokasi (lebar 25)
        ceil(strlen($pelapor) / 15),     // Kolom pelapor (lebar 25)
        ceil(strlen($teknisi) / 15),     // Kolom teknisi (lebar 25)
        ceil(strlen($keterangan) / 60)   // Kolom keterangan (lebar 92)
    );

    // Menghitung tinggi baris dinamis
    $tinggi = ($maxLine * 5) + 4;

    if ($pdf->GetY() + $tinggi > 185) {
        $pdf->AddPage();
        cetakHeaderPerbaikan($pdf, $w, $header);
    }

    $x = $pdf->GetX();
    $y = $pdf->GetY();

    $currentX = $x;
    foreach ($w as $width) {
        $pdf->Rect($currentX, $y, $width, $tinggi);
        $currentX += $width;
    }

    $pdf->SetXY($x, $y + 2);
    $pdf->Cell($w[0], 5, $no++, 0, 0, 'C');

    $pdf->SetXY($x + $w[0] + 1, $y + 2);
    $pdf->MultiCell($w[1] - 2, 5, $nama_aset, 0, 'L');

    $pdf->SetXY($x + $w[0] + $w[1] + 1, $y + 2);
    $pdf->MultiCell($w[2] - 2, 5, $lokasi, 0, 'L');

    $pdf->SetXY($x + $w[0] + $w[1] + $w[2], $y + 2);
    $pdf->Cell($w[3], 5, $kategori, 0, 0, 'C');

    $pdf->SetXY($x + $w[0] + $w[1] + $w[2] + $w[3] + 1, $y + 2);
    $pdf->MultiCell($w[4] - 2, 5, $pelapor, 0, 'L');

    $pdf->SetXY($x + $w[0] + $w[1] + $w[2] + $w[3] + $w[4] + 1, $y + 2);
    $pdf->MultiCell($w[5] - 2, 5, $teknisi, 0, 'L');

    $pdf->SetXY($x + $w[0] + $w[1] + $w[2] + $w[3] + $w[4] + $w[5], $y + 2);
    $pdf->Cell($w[6], 5, $tanggal, 0, 0, 'C');

    $pdf->SetXY($x + $w[0] + $w[1] + $w[2] + $w[3] + $w[4] + $w[5] + $w[6], $y + 2);
    $pdf->Cell($w[7], 5, $status, 0, 0, 'C');

    $pdf->SetXY($x + $w[0] + $w[1] + $w[2] + $w[3] + $w[4] + $w[5] + $w[6] + $w[7] + 1, $y + 2);
    $pdf->MultiCell($w[8] - 2, 5, $keterangan, 0, 'L');

    // Geser Y ke bawah sesuai tinggi baris yang baru
    $pdf->SetY($y + $tinggi);
}
// ==================== AREA TANDA TANGAN ==================== //
// Mengubah batas toleransi dari 155 ke 140 agar area tanda tangan tidak kepotong
if ($pdf->GetY() > 140) {
    $pdf->AddPage();
}

// ==================== TANDA TANGAN (2 KOLOM) ==================== //
renderReportSignature($pdf, $koneksi);

ob_end_clean();
$pdf->Output('I', 'Laporan_Perbaikan_' . date('Ymd_His') . '.pdf');
exit;
