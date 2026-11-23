<?php
session_start();

if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../login.php");
    exit;
}

require(__DIR__ . '/../config/fpdf.php');
include(__DIR__ . '/../config/koneksi.php');
require_once(__DIR__ . '/../config/ttd_helper.php');

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

class PDF_Opname extends FPDF
{
    // Footer saja agar Kop Surat TIDAK terulang/terdouble di setiap halaman baru
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . ' dari {nb} | SIMARIS RS Bhayangkara Banjarmasin', 0, 0, 'L');
        $this->Cell(0, 10, 'Dicetak: ' . date('d/m/Y H:i:s'), 0, 0, 'R');
    }
}

$pdf = new PDF_Opname('L', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(false); // Kelola manual agar jika ada halaman berikutnya, hanya header tabel yang tercetak
$pdf->AddPage();

// ================= KOP SURAT (HANYA DI HALAMAN 1) ================= //
renderOfficialKopSurat($pdf, 'L');

// ================= JUDUL LAPORAN ================= //
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 6, 'LAPORAN HASIL STOCK OPNAME ASET (SELISIH FISIK VS SISTEM)', 0, 1, 'C');
$pdf->SetFont('Arial', '', 9);

$info = "Dicetak pada: " . date('d/m/Y H:i');
if (!empty($periode_filter)) $info .= " | Periode: $periode_filter";
if (!empty($status_filter))  $info .= " | Status: $status_filter";
$pdf->Cell(0, 5, $info, 0, 1, 'C');
$pdf->Ln(4);

// ================= HEADER TABEL ================= //
// Total width: 8 + 24 + 50 + 18 + 26 + 32 + 16 + 16 + 14 + 22 + 18 + 33 = 277 mm
$widths = [8, 24, 50, 18, 26, 32, 16, 16, 14, 22, 18, 33];

function cetakHeaderTabelOpname($pdf, $widths)
{
    $pdf->SetFont('Arial', 'B', 7.5);
    $pdf->SetFillColor(72, 201, 176);
    $pdf->SetTextColor(255);

    $pdf->Cell($widths[0], 9, 'No', 1, 0, 'C', true);
    $pdf->Cell($widths[1], 9, 'Kode Opname', 1, 0, 'C', true);
    $pdf->Cell($widths[2], 9, 'Nama Aset', 1, 0, 'C', true);
    $pdf->Cell($widths[3], 9, 'Tanggal', 1, 0, 'C', true);
    $pdf->Cell($widths[4], 9, 'Periode', 1, 0, 'C', true);
    $pdf->Cell($widths[5], 9, 'Lokasi Cek', 1, 0, 'C', true);
    $pdf->Cell($widths[6], 9, 'Stok Sis', 1, 0, 'C', true);
    $pdf->Cell($widths[7], 9, 'Stok Fis', 1, 0, 'C', true);
    $pdf->Cell($widths[8], 9, 'Selisih', 1, 0, 'C', true);
    $pdf->Cell($widths[9], 9, 'Status', 1, 0, 'C', true);
    $pdf->Cell($widths[10], 9, 'Kondisi', 1, 0, 'C', true);
    $pdf->Cell($widths[11], 9, 'Pemeriksa', 1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 7.5);
    $pdf->SetTextColor(0);
}

cetakHeaderTabelOpname($pdf, $widths);

// ================= DATA LOOPING ================= //
$no = 1;
$tot_sistem = 0;
$tot_fisik = 0;
$tot_selisih = 0;

if (mysqli_num_rows($res) == 0) {
    $pdf->Cell(array_sum($widths), 10, 'Tidak ada data stock opname', 1, 1, 'C');
} else {
    while ($r = mysqli_fetch_assoc($res)) {
        $selisih = (int)$r['selisih'];
        $tot_sistem += (int)$r['stok_sistem'];
        $tot_fisik += (int)$r['stok_fisik'];
        $tot_selisih += $selisih;

        $selisih_str = ($selisih > 0) ? '+' . $selisih : (string)$selisih;

        // Cek jika baris melebihi halaman (185mm batas aman A4 Landscape)
        if ($pdf->GetY() + 7 > 185) {
            $pdf->AddPage();
            // Hanya cetak header tabel pada halaman sambungan (TIDAK mencetak Kop Surat lagi)
            cetakHeaderTabelOpname($pdf, $widths);
        }

        $pdf->Cell($widths[0], 7, $no++, 1, 0, 'C');
        $pdf->Cell($widths[1], 7, $r['kode_opname'], 1, 0, 'C');
        $pdf->Cell($widths[2], 7, substr($r['nama_aset'], 0, 30), 1, 0, 'L');
        $pdf->Cell($widths[3], 7, date('d/m/y', strtotime($r['tanggal_opname'])), 1, 0, 'C');
        $pdf->Cell($widths[4], 7, substr($r['periode_opname'], 0, 16), 1, 0, 'C');
        $pdf->Cell($widths[5], 7, substr($r['lokasi_opname'], 0, 20), 1, 0, 'L');
        $pdf->Cell($widths[6], 7, $r['stok_sistem'], 1, 0, 'C');
        $pdf->Cell($widths[7], 7, $r['stok_fisik'], 1, 0, 'C');
        $pdf->Cell($widths[8], 7, $selisih_str, 1, 0, 'C');
        $pdf->Cell($widths[9], 7, $r['status_selisih'], 1, 0, 'C');
        $pdf->Cell($widths[10], 7, $r['kondisi_fisik'], 1, 0, 'C');
        $pdf->Cell($widths[11], 7, substr($r['petugas_opname'], 0, 20), 1, 1, 'L');
    }

    // Baris Total
    if ($pdf->GetY() + 8 > 185) {
        $pdf->AddPage();
        cetakHeaderTabelOpname($pdf, $widths);
    }

    $pdf->SetFont('Arial', 'B', 7.5);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell($widths[0] + $widths[1] + $widths[2] + $widths[3] + $widths[4] + $widths[5], 8, 'TOTAL KESELURUHAN :', 1, 0, 'R', true);
    $pdf->Cell($widths[6], 8, $tot_sistem, 1, 0, 'C', true);
    $pdf->Cell($widths[7], 8, $tot_fisik, 1, 0, 'C', true);
    $pdf->Cell($widths[8], 8, ($tot_selisih > 0 ? '+' : '') . $tot_selisih, 1, 0, 'C', true);
    $pdf->Cell($widths[9] + $widths[10] + $widths[11], 8, '', 1, 1, 'C', true);
}

// ================= TANDA TANGAN 2 KOLOM RESMI ================= //
renderReportSignature($pdf, $koneksi);

// ================= OUTPUT PDF ================= //
ob_clean();
$pdf->Output('I', 'Laporan_Stock_Opname_' . date('Ymd_His') . '.pdf');
exit;
