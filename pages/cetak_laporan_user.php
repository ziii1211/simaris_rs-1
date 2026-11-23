<?php
session_start();
if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../login.php");
    exit;
}

// Hindari error output sebelum PDF
ob_start();

require(__DIR__ . '/../config/fpdf.php');
include(__DIR__ . '/../config/koneksi.php');
require_once(__DIR__ . '/../config/ttd_helper.php');

// ==================== FILTER DATA ==================== //
$search  = $_GET['search'] ?? '';
$status  = $_GET['status'] ?? '';
$role    = $_GET['role'] ?? '';

$where = [];
if ($search != '')  $where[] = "(nama_pengguna LIKE '%" . mysqli_real_escape_string($koneksi, $search) . "%' OR username LIKE '%" . mysqli_real_escape_string($koneksi, $search) . "%')";
if ($status != '')  $where[] = "status = '" . mysqli_real_escape_string($koneksi, $status) . "'";
if ($role != '')    $where[] = "role = '" . mysqli_real_escape_string($koneksi, $role) . "'";

$whereSQL = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// ==================== QUERY DATA ==================== //
$sql = "SELECT * FROM pengguna $whereSQL ORDER BY nama_pengguna ASC";
$res = mysqli_query($koneksi, $sql);

// ==================== SETUP PDF ==================== //
$pdf = new FPDF('L', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(false);

renderOfficialKopSurat($pdf, 'L');

$pdf->SetFont('Arial', 'B', 13);
$pdf->Cell(0, 7, 'LAPORAN MANAJEMEN USER & HAK AKSES', 0, 1, 'C');
$pdf->Ln(4);
// ==================== TABEL HEADER ==================== //
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetFillColor(72, 201, 176);
$pdf->SetTextColor(255);
$header = ['No', 'Nama Pengguna', 'Username', 'Level', 'Role', 'Status'];
$widths = [12, 60, 60, 40, 40, 40];
foreach ($header as $i => $col) {
    $pdf->Cell($widths[$i], 11, $col, 1, 0, 'C', true);
}
$pdf->Ln();

// ==================== TABEL ISI ==================== //
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(0);
$no = 1;

while ($r = mysqli_fetch_assoc($res)) {
    $pdf->Cell($widths[0], 9, $no++, 1, 0, 'C');
    $pdf->Cell($widths[1], 9, $r['nama_pengguna'], 1);
    $pdf->Cell($widths[2], 9, $r['username'], 1);
    $pdf->Cell($widths[3], 9, $r['level'], 1);
    $pdf->Cell($widths[4], 9, $r['role'], 1);
    $pdf->Cell($widths[5], 9, $r['status'], 1, 1);
}

// ==================== TANDA TANGAN (2 KOLOM) ==================== //
renderReportSignature($pdf, $koneksi);

// ==================== OUTPUT PDF ==================== //
ob_end_clean();
$pdf->Output('I', 'Laporan_Perawatan_' . date('Ymd_His') . '.pdf');
exit;
