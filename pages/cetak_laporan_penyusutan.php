<?php
session_start();

if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../login.php");
    exit;
}

require(__DIR__ . '/../config/fpdf.php');
include(__DIR__ . '/../config/koneksi.php');
require_once(__DIR__ . '/../config/ttd_helper.php');

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

class PDF_Penyusutan extends FPDF
{
    // Footer saja agar Kop Surat TIDAK terulang/terdouble di setiap halaman baru
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . ' dari {nb} | Sistem Informasi Manajemen Aset & Fasilitas (SIMARIS)', 0, 0, 'L');
        $this->Cell(0, 10, 'Dicetak: ' . date('d/m/Y H:i:s'), 0, 0, 'R');
    }
}

$pdf = new PDF_Penyusutan('L', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(false); // Kita kelola page break manual agar header tabel rapi tanpa kop surat dobel
$pdf->AddPage();

// ================= KOP SURAT (HANYA DI HALAMAN 1) ================= //
renderOfficialKopSurat($pdf, 'L');

// ================= JUDUL LAPORAN ================= //
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 6, 'LAPORAN REKAPITULASI PENYUSUTAN NILAI ASET (PERIODE TAHUN ' . $periode_tahun . ')', 0, 1, 'C');
$pdf->SetFont('Arial', '', 9);

$filterText = "Metode: Garis Lurus (Straight-Line) | Periode Buku: Tahun $periode_tahun";
if (!empty($kategoriFilter)) $filterText .= " | Kategori: $kategoriFilter";
$pdf->Cell(0, 5, $filterText, 0, 1, 'C');
$pdf->Ln(4);

// ================= HEADER TABEL ================= //
// Lebar Kolom Total 277 mm (A4 Landscape = 297 - 20)
$widths = [8, 56, 22, 36, 18, 10, 29, 26, 26, 28, 18];

function cetakHeaderTabelPenyusutan($pdf, $widths, $periode_tahun)
{
    $pdf->SetFont('Arial', 'B', 7.5);
    $pdf->SetFillColor(72, 201, 176);
    $pdf->SetTextColor(255);

    $pdf->Cell($widths[0], 8, 'No', 1, 0, 'C', true);
    $pdf->Cell($widths[1], 8, 'Nama Aset', 1, 0, 'C', true);
    $pdf->Cell($widths[2], 8, 'Kategori', 1, 0, 'C', true);
    $pdf->Cell($widths[3], 8, 'Lokasi', 1, 0, 'C', true);
    $pdf->Cell($widths[4], 8, 'Thn/Umur', 1, 0, 'C', true);
    $pdf->Cell($widths[5], 8, 'Qty', 1, 0, 'C', true);
    $pdf->Cell($widths[6], 8, 'Nilai Perolehan', 1, 0, 'C', true);
    $pdf->Cell($widths[7], 8, 'Beban Susut ' . $periode_tahun, 1, 0, 'C', true);
    $pdf->Cell($widths[8], 8, 'Akum. Susut', 1, 0, 'C', true);
    $pdf->Cell($widths[9], 8, 'Nilai Buku Akhir', 1, 0, 'C', true);
    $pdf->Cell($widths[10], 8, 'Status', 1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 7.5);
    $pdf->SetTextColor(0);
}

cetakHeaderTabelPenyusutan($pdf, $widths, $periode_tahun);

// ================= DATA LOOPING ================= //
$no = 1;
$sum_perolehan = 0;
$sum_beban = 0;
$sum_akum = 0;
$sum_nb = 0;

if (mysqli_num_rows($res) == 0) {
    $pdf->Cell(array_sum($widths), 10, 'Tidak ada data aset pada periode ini', 1, 1, 'C');
} else {
    while ($r = mysqli_fetch_assoc($res)) {
        $stok = (int)($r['total_stok'] ?? 0);
        $h_total = (float)$r['harga'] * $stok;
        $umur = (int)($r['umur_ekonomis'] ?? 0);
        $tgl_masuk = $r['tanggal_masuk'];
        $thn_masuk = (int)date('Y', strtotime($tgl_masuk));
        $kategori = $r['kategori_aset'] ?? '-';

        if ($periode_tahun < $thn_masuk) {
            $beban = 0;
            $akum = 0;
            $nb = 0;
            $status_str = 'Belum Ada';
        } elseif ($umur > 0) {
            $d_tahun = $h_total / $umur;
            $thn_awal = max(0, min($umur, $periode_tahun - $thn_masuk));
            $beban = ($thn_awal < $umur) ? $d_tahun : 0;
            $thn_akhir = max(0, min($umur, $periode_tahun - $thn_masuk + 1));
            $akum = $thn_akhir * $d_tahun;
            $nb = max(0, $h_total - $akum);

            $status_str = ($thn_akhir < $umur) ? 'Aktif' : 'Habis';
        } else {
            $beban = 0;
            $akum = 0;
            $nb = $h_total;
            $status_str = 'Tanpa Susut';
        }

        $sum_perolehan += $h_total;
        $sum_beban += $beban;
        $sum_akum += $akum;
        $sum_nb += $nb;

        $thn_umur_str = $thn_masuk . ' (' . ($umur > 0 ? $umur . 'th' : '-') . ')';

        // Cek jika baris akan melebihi halaman (185mm batas aman A4 Landscape)
        if ($pdf->GetY() + 7 > 185) {
            $pdf->AddPage();
            // Hanya cetak header tabel pada halaman sambungan (TIDAK mencetak Kop Surat lagi)
            cetakHeaderTabelPenyusutan($pdf, $widths, $periode_tahun);
        }

        $pdf->Cell($widths[0], 7, $no++, 1, 0, 'C');
        $pdf->Cell($widths[1], 7, substr($r['nama_aset'], 0, 32), 1, 0, 'L');
        $pdf->Cell($widths[2], 7, substr($kategori, 0, 14), 1, 0, 'C');
        $pdf->Cell($widths[3], 7, substr($r['lokasi'] ?? '-', 0, 22), 1, 0, 'L');
        $pdf->Cell($widths[4], 7, $thn_umur_str, 1, 0, 'C');
        $pdf->Cell($widths[5], 7, $stok, 1, 0, 'C');
        $pdf->Cell($widths[6], 7, number_format($h_total, 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell($widths[7], 7, number_format($beban, 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell($widths[8], 7, number_format($akum, 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell($widths[9], 7, number_format($nb, 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell($widths[10], 7, $status_str, 1, 1, 'C');
    }

    // Baris Total
    if ($pdf->GetY() + 8 > 185) {
        $pdf->AddPage();
        cetakHeaderTabelPenyusutan($pdf, $widths, $periode_tahun);
    }

    $pdf->SetFont('Arial', 'B', 7.5);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell($widths[0] + $widths[1] + $widths[2] + $widths[3] + $widths[4] + $widths[5], 8, 'TOTAL KESELURUHAN (PERIODE TAHUN ' . $periode_tahun . ') :', 1, 0, 'R', true);
    $pdf->Cell($widths[6], 8, number_format($sum_perolehan, 0, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell($widths[7], 8, number_format($sum_beban, 0, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell($widths[8], 8, number_format($sum_akum, 0, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell($widths[9], 8, number_format($sum_nb, 0, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell($widths[10], 8, '', 1, 1, 'C', true);
}

// ================= TANDA TANGAN 2 KOLOM RESMI ================= //
renderReportSignature($pdf, $koneksi);

// ================= OUTPUT PDF ================= //
ob_clean();
$pdf->Output('I', 'Laporan_Penyusutan_Aset_Periode_' . $periode_tahun . '_' . date('Ymd_His') . '.pdf');
exit;
