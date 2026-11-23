<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../login.php");
    exit;
}

require_once(__DIR__ . '/../config/koneksi.php');
require_once(__DIR__ . '/../config/fpdf.php');
require_once(__DIR__ . '/../config/ttd_helper.php');

$tahun_aktif = date('Y');
$tahun_filter = isset($_GET['tahun']) && !empty($_GET['tahun']) ? (int)$_GET['tahun'] : (int)$tahun_aktif;
$kategori_filter = isset($_GET['kategori']) ? mysqli_real_escape_string($koneksi, $_GET['kategori']) : '';

// 1. Ambil Ringkasan Anggaran & Aset
$whereKat = !empty($kategori_filter) ? "WHERE kategori_aset = '$kategori_filter'" : "";
$qAset = mysqli_query($koneksi, "SELECT 
    id_aset, kode_aset, nama_aset, kategori_aset, lokasi, harga, total_stok, stok_rusak, stok_perawatan, stok_tersedia,
    umur_ekonomis, YEAR(tanggal_masuk) as thn_masuk
FROM aset 
$whereKat
ORDER BY lokasi ASC, nama_aset ASC");

$totalNilaiRS = 0;
$totalUnitRS = 0;
$totalCapex = 0;
$listPrioritasGanti = [];

while ($a = mysqli_fetch_assoc($qAset)) {
    $thnMasuk = (int)$a['thn_masuk'];
    $umurPakai = max(0, $tahun_filter - $thnMasuk);
    $umurEko = (int)$a['umur_ekonomis'];
    $sisaUmur = $umurEko - $umurPakai;
    $nilaiItem = (float)$a['harga'] * (int)$a['total_stok'];

    $totalNilaiRS += $nilaiItem;
    $totalUnitRS += (int)$a['total_stok'];

    // Kriteria Prioritas Ganti: Sisa umur <= 1 ATAU ada unit rusak
    if ($sisaUmur <= 1 || (int)$a['stok_rusak'] > 0) {
        $totalCapex += $nilaiItem;
        $a['umur_pakai'] = $umurPakai;
        $a['sisa_umur'] = $sisaUmur;
        $a['nilai_total'] = $nilaiItem;
        $a['prioritas'] = ($sisaUmur <= 0 || $a['stok_rusak'] > 0) ? 'Prioritas 1 (Mendesak)' : 'Prioritas 2 (Siaga)';
        $listPrioritasGanti[] = $a;
    }
}

// 2. Ambil Total Biaya Pemeliharaan & Perbaikan Historis
$qMaint = mysqli_query($koneksi, "SELECT SUM(biaya_perbaikan) as tot_rusak FROM kerusakan");
$rMaint1 = mysqli_fetch_assoc($qMaint);
$totBiayaPerbaikan = (float)($rMaint1['tot_rusak'] ?? 0);

$qRawat = mysqli_query($koneksi, "SELECT SUM(biaya_pemeliharaan) as tot_rawat FROM perawatan");
$rMaint2 = mysqli_fetch_assoc($qRawat);
$totBiayaPerawatan = (float)($rMaint2['tot_rawat'] ?? 0);

$totBiayaMaintHistoris = $totBiayaPerbaikan + $totBiayaPerawatan;
$totalOpex = $totalNilaiRS * 0.05; // 5% rekomendasi tahunan

class PDF_Analisis extends FPDF
{
    function renderKopSurat()
    {
        $logo1 = realpath(__DIR__ . '/../assets/img/logo_dokpol.png');
        $logo2 = realpath(__DIR__ . '/../assets/img/logo_rs.jpg');

        if ($logo1 && file_exists($logo1)) {
            $this->Image($logo1, 15, 10, 22);
        }
        if ($logo2 && file_exists($logo2)) {
            $this->Image($logo2, 260, 10, 22);
        }

        $this->SetY(10);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 5, 'KEPOLISIAN NEGARA REPUBLIK INDONESIA', 0, 1, 'C');
        $this->Cell(0, 5, 'DAERAH KALIMANTAN SELATAN', 0, 1, 'C');
        $this->Cell(0, 5, 'BIDANG KEDOKTERAN DAN KESEHATAN', 0, 1, 'C');
        $this->SetFont('Arial', 'B', 13);
        $this->Cell(0, 6, 'RUMAH SAKIT BHAYANGKARA TK. III BANJARMASIN', 0, 1, 'C');
        $this->SetFont('Arial', '', 8.5);
        $this->Cell(0, 4, 'Jl. A. Yani Km. 3.5 No. 477 Banjarmasin | Telp: (0511) 3254143', 0, 1, 'C');

        // Garis Ganda Kop
        $this->SetLineWidth(0.8);
        $this->Line(15, 38, 282, 38);
        $this->SetLineWidth(0.2);
        $this->Line(15, 39, 282, 39);
        $this->Ln(5);
    }

    function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(150, 6, 'SIMARIS RS Bhayangkara Tk. III Banjarmasin - Laporan Analisis & Anggaran Aset', 0, 0, 'L');
        $this->Cell(0, 6, 'Halaman ' . $this->PageNo() . ' dari {nb}', 0, 0, 'R');
    }
}

$pdf = new PDF_Analisis('L', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(15, 10, 15);
$pdf->SetAutoPageBreak(true, 18);

$pdf->AddPage();
$pdf->renderKopSurat();

// Judul Dokumen
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(0, 6, 'LAPORAN ANALISIS ASET & REKOMENDASI PERENCANAAN ANGGARAN', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(13, 148, 136);
$subJudul = 'PROYEKSI KEBUTUHAN BELANJA MODAL (CAPEX) & PEMELIHARAAN (OPEX) TAHUN ' . $tahun_filter;
if (!empty($kategori_filter)) {
    $subJudul .= ' - KATEGORI: ' . strtoupper($kategori_filter);
}
$pdf->Cell(0, 5, $subJudul, 0, 1, 'C');
$pdf->Ln(4);

// Box Ringkasan Eksekutif Anggaran (4 Kolom)
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetFillColor(241, 245, 249);
$pdf->SetTextColor(30, 41, 59);

$boxW = 66.75;
$pdf->Cell($boxW, 6, 'NILAI INVESTASI AKTIF', 1, 0, 'C', true);
$pdf->Cell($boxW, 6, 'BIAYA PEMELIHARAAN HISTORIS', 1, 0, 'C', true);
$pdf->Cell($boxW, 6, 'USULAN CAPEX PENGGANTIAN', 1, 0, 'C', true);
$pdf->Cell($boxW, 6, 'ESTIMASI OPEX PEMELIHARAAN', 1, 1, 'C', true);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell($boxW, 7, 'Rp ' . number_format($totalNilaiRS, 0, ',', '.'), 1, 0, 'C');
$pdf->Cell($boxW, 7, 'Rp ' . number_format($totBiayaMaintHistoris, 0, ',', '.'), 1, 0, 'C');
$pdf->SetTextColor(220, 38, 38);
$pdf->Cell($boxW, 7, 'Rp ' . number_format($totalCapex, 0, ',', '.'), 1, 0, 'C');
$pdf->SetTextColor(180, 83, 9);
$pdf->Cell($boxW, 7, 'Rp ' . number_format($totalOpex, 0, ',', '.'), 1, 1, 'C');
$pdf->Ln(4);

// Section 1: Daftar Aset Prioritas Penggantian
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(0, 6, '1. DAFTAR ASET PRIORITAS PENGGANTIAN (END-OF-LIFE & KELAYAKAN TEKNIS)', 0, 1, 'L');

$pdf->SetFont('Arial', 'B', 8);
$pdf->SetFillColor(72, 201, 176);
$pdf->SetTextColor(255);

$pdf->Cell(8, 7, 'NO', 1, 0, 'C', true);
$pdf->Cell(25, 7, 'KODE ASET', 1, 0, 'C', true);
$pdf->Cell(62, 7, 'NAMA BARANG / ALKES', 1, 0, 'C', true);
$pdf->Cell(50, 7, 'LOKASI RUANGAN', 1, 0, 'C', true);
$pdf->Cell(20, 7, 'THN MASUK', 1, 0, 'C', true);
$pdf->Cell(25, 7, 'SISA UMUR', 1, 0, 'C', true);
$pdf->Cell(37, 7, 'ESTIMASI ANGGARAN', 1, 0, 'C', true);
$pdf->Cell(40, 7, 'REKOMENDASI KEPUTUSAN', 1, 1, 'C', true);
$pdf->SetTextColor(0);

$pdf->SetFont('Arial', '', 8);
if (empty($listPrioritasGanti)) {
    $pdf->Cell(267, 7, 'Tidak ada aset yang terindikasi kritis / habis masa ekonomis untuk periode ini.', 1, 1, 'C');
} else {
    $no = 1;
    foreach ($listPrioritasGanti as $lp) {
        $pdf->Cell(8, 6.5, $no++, 1, 0, 'C');
        $pdf->Cell(25, 6.5, $lp['kode_aset'], 1, 0, 'C');
        $namaP = strlen($lp['nama_aset']) > 35 ? substr($lp['nama_aset'], 0, 33) . '..' : $lp['nama_aset'];
        $pdf->Cell(62, 6.5, $namaP, 1, 0, 'L');
        $lokP = strlen($lp['lokasi']) > 28 ? substr($lp['lokasi'], 0, 26) . '..' : $lp['lokasi'];
        $pdf->Cell(50, 6.5, $lokP, 1, 0, 'L');
        $pdf->Cell(20, 6.5, $lp['thn_masuk'], 1, 0, 'C');
        $pdf->Cell(25, 6.5, ($lp['sisa_umur'] <= 0 ? 'Habis (0 Thn)' : $lp['sisa_umur'] . ' Thn'), 1, 0, 'C');
        $pdf->Cell(37, 6.5, 'Rp ' . number_format($lp['nilai_total'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell(40, 6.5, $lp['prioritas'], 1, 1, 'C');
    }
}
$pdf->Ln(4);

// Section 2: Rekapitulasi per Unit Pelayanan
if ($pdf->GetY() > 140) {
    $pdf->AddPage();
}

$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(0, 6, '2. SEBARAN NILAI INVESTASI & KONDISI FISIK PER RUANGAN / UNIT PELAYANAN', 0, 1, 'L');

$pdf->SetFont('Arial', 'B', 8);
$pdf->SetFillColor(72, 201, 176);
$pdf->SetTextColor(255);

$pdf->Cell(8, 7, 'NO', 1, 0, 'C', true);
$pdf->Cell(95, 7, 'NAMA RUANGAN / UNIT PELAYANAN', 1, 0, 'C', true);
$pdf->Cell(28, 7, 'TOTAL UNIT', 1, 0, 'C', true);
$pdf->Cell(25, 7, 'UNIT BAIK', 1, 0, 'C', true);
$pdf->Cell(25, 7, 'RUSAK/RAWAT', 1, 0, 'C', true);
$pdf->Cell(36, 7, 'KEANDALAN (%)', 1, 0, 'C', true);
$pdf->Cell(50, 7, 'TOTAL NILAI ASET', 1, 1, 'C', true);
$pdf->SetTextColor(0);

$qUnitRep = mysqli_query($koneksi, "SELECT 
    lokasi,
    SUM(total_stok) as total_unit,
    SUM(stok_tersedia) as unit_baik,
    SUM(stok_rusak + stok_perawatan) as unit_masalah,
    SUM(harga * total_stok) as total_nilai
FROM aset 
$whereKat
GROUP BY lokasi 
ORDER BY total_nilai DESC 
LIMIT 12");

$pdf->SetFont('Arial', '', 8);
$noU = 1;
while ($ru = mysqli_fetch_assoc($qUnitRep)) {
    $totU = (int)$ru['total_unit'];
    $baikU = (int)$ru['unit_baik'];
    $keandalan = $totU > 0 ? round(($baikU / $totU) * 100, 1) : 0;

    $pdf->Cell(8, 6, $noU++, 1, 0, 'C');
    $pdf->Cell(95, 6, $ru['lokasi'], 1, 0, 'L');
    $pdf->Cell(28, 6, $totU . ' Unit', 1, 0, 'C');
    $pdf->Cell(25, 6, $baikU . ' Unit', 1, 0, 'C');
    $pdf->Cell(25, 6, $ru['unit_masalah'] . ' Unit', 1, 0, 'C');
    $pdf->Cell(36, 6, $keandalan . '%', 1, 0, 'C');
    $pdf->Cell(50, 6, 'Rp ' . number_format($ru['total_nilai'], 0, ',', '.'), 1, 1, 'R');
}

// Section 3: Catatan & Rekomendasi Manajerial
$pdf->Ln(3);
$pdf->SetFont('Arial', 'I', 8);
$pdf->SetTextColor(75, 85, 99);
$pdf->MultiCell(0, 4, 'Catatan Pengambilan Keputusan: Data di atas dihasilkan secara otomatis oleh Decision Support System SIMARIS RS Bhayangkara untuk mendukung penyusunan Rencana Bisnis dan Anggaran (RBA). Aset kategori Prioritas 1 direkomendasikan masuk dalam usulan Belanja Modal (CAPEX) tahun ' . $tahun_filter . ', sedangkan alokasi OPEX 5% disarankan untuk menjamin pemeliharaan preventif dan kalibrasi alkes vital.', 0, 'L');

// Tanda Tangan Resmi 2 Kolom
renderReportSignature($pdf, $koneksi, [
    'pj_jabatan' => 'Koordinator Sarpras & Pengelola Aset',
    'pj_nama'    => $_SESSION['nama_pengguna'] ?? 'Petugas Sarpras'
]);

$pdf->Output('I', 'Laporan_Analisis_Anggaran_Aset_' . date('Ymd_His') . '.pdf');
