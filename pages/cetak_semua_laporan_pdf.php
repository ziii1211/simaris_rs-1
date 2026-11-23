<?php
session_start();
if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../login.php");
    exit;
}

ob_clean(); // Mencegah error biner PDF corrupt
require(__DIR__ . '/../config/fpdf.php');
include(__DIR__ . '/../config/koneksi.php');
require_once(__DIR__ . '/../config/ttd_helper.php');

$tahun_filter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : '';

// ==================== SETUP LAYOUT PDF ==================== //
$pdf = new FPDF('L', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(false);

// ==================== KOP SURAT RESMI POLRI ==================== //
renderOfficialKopSurat($pdf, 'L');

$pdf->SetFont('Arial', 'B', 13);
$pdf->Cell(0, 7, 'LAPORAN REKAPITULASI KESELURUHAN DATA ASET & FASILITAS (17 LAPORAN)', 0, 1, 'C');
if ($tahun_filter != '') {
    $pdf->SetFont('Arial', 'I', 9.5);
    $pdf->Cell(0, 5, 'Periode Registrasi Tahun: ' . $tahun_filter, 0, 1, 'C');
}
$pdf->Ln(4);

// =========================================================================
// SECTION 1: LAPORAN ASET 
// =========================================================================
$w_aset = [8, 40, 15, 18, 18, 20, 16, 28, 18, 25, 14, 20, 37];
$header_aset = ['No', 'Nama Aset', 'Kategori', 'Jenis', 'Tipe', 'Lokasi Ruangan', 'Total Stok', 'Rincian Ketersediaan', 'Asal Usul', 'Harga Perolehan', 'Umur Eko.', 'Tanggal Masuk', 'Dokumen'];

function cetakHeaderTabelAsetLengkap($pdf, $w, $header)
{
    $pdf->SetFont('Arial', 'B', 5.5);
    $pdf->SetFillColor(72, 201, 176);
    $pdf->SetTextColor(255);
    for ($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 9, $header[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 6);
    $pdf->SetTextColor(0);
}

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, '1. LAPORAN ASET', 0, 1, 'L');
cetakHeaderTabelAsetLengkap($pdf, $w_aset, $header_aset);

$where_aset = [];
if (!empty($tahun_filter)) $where_aset[] = "YEAR(tanggal_masuk) = '$tahun_filter'";
$whereSQL_aset = count($where_aset) ? 'WHERE ' . implode(' AND ', $where_aset) : '';
$sql_aset = "SELECT * FROM aset $whereSQL_aset ORDER BY id_aset DESC";
$res_aset = mysqli_query($koneksi, $sql_aset);

if (mysqli_num_rows($res_aset) == 0) {
    $pdf->Cell(277, 8, 'Tidak ada data', 1, 1, 'C');
} else {
    $no_aset = 1;
    while ($r = mysqli_fetch_assoc($res_aset)) {
        $nama_aset  = $r['nama_aset'] ?? '-';
        $kategori   = $r['kategori_aset'] ?? '-';
        $jenis      = $r['jenis'] ?? '-';
        $tipe       = $r['tipe_aset'] ?? '-';
        $lokasi     = $r['lokasi'] ?? '-';
        $stok       = isset($r['total_stok']) ? $r['total_stok'] : (isset($r['stok']) ? $r['stok'] : '0');
        $rincian    = "Tersedia: " . ($r['stok_tersedia'] ?? '0') . "\nRusak: " . ($r['stok_rusak'] ?? '0') . "\nRawat: " . ($r['stok_perawatan'] ?? '0');
        $asal       = $r['asal_usul'] ?? '-';
        $harga      = 'Rp ' . number_format($r['harga'], 0, ',', '.');
        $umur       = (isset($r['umur_ekonomis']) && $r['umur_ekonomis'] > 0) ? $r['umur_ekonomis'] . ' Th' : '-';

        $tgl_masuk  = $r['tanggal_masuk'] ?? '';
        $tgl        = (!$tgl_masuk || $tgl_masuk == '0000-00-00') ? '-' : date('d-m-Y', strtotime($tgl_masuk));

        $maxLine = max(
            3,
            ceil(strlen($nama_aset) / 30),
            ceil(strlen($jenis) / 15),
            ceil(strlen($tipe) / 15),
            ceil(strlen($lokasi) / 15),
            ceil(strlen($asal) / 15)
        );
        $tinggi = ($maxLine * 4) + 4;

        if ($pdf->GetY() + $tinggi > 185) {
            $pdf->AddPage();
            cetakHeaderTabelAsetLengkap($pdf, $w_aset, $header_aset);
        }

        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $sum_w = 0;
        for ($i = 0; $i < 13; $i++) {
            $pdf->Rect($x + $sum_w, $y, $w_aset[$i], $tinggi);
            $sum_w += $w_aset[$i];
        }

        $pdf->SetXY($x, $y + 2);
        $pdf->Cell($w_aset[0], 4, $no_aset++, 0, 0, 'C');

        $pdf->SetXY($x + $w_aset[0] + 1, $y + 2);
        $pdf->MultiCell($w_aset[1] - 2, 4, $nama_aset, 0, 'L');

        $pdf->SetXY($x + $w_aset[0] + $w_aset[1], $y + 2);
        $pdf->Cell($w_aset[2], 4, $kategori, 0, 0, 'C');

        $pdf->SetXY($x + $w_aset[0] + $w_aset[1] + $w_aset[2] + 1, $y + 2);
        $pdf->MultiCell($w_aset[3] - 2, 4, $jenis, 0, 'L');

        $pdf->SetXY($x + $w_aset[0] + $w_aset[1] + $w_aset[2] + $w_aset[3] + 1, $y + 2);
        $pdf->MultiCell($w_aset[4] - 2, 4, $tipe, 0, 'L');

        $pdf->SetXY($x + $w_aset[0] + $w_aset[1] + $w_aset[2] + $w_aset[3] + $w_aset[4] + 1, $y + 2);
        $pdf->MultiCell($w_aset[5] - 2, 4, $lokasi, 0, 'L');

        $pdf->SetXY($x + $w_aset[0] + $w_aset[1] + $w_aset[2] + $w_aset[3] + $w_aset[4] + $w_aset[5], $y + 2);
        $pdf->Cell($w_aset[6], 4, $stok, 0, 0, 'C');

        $pdf->SetXY($x + $w_aset[0] + $w_aset[1] + $w_aset[2] + $w_aset[3] + $w_aset[4] + $w_aset[5] + $w_aset[6] + 1, $y + 2);
        $pdf->MultiCell($w_aset[7] - 2, 4, $rincian, 0, 'L');

        $pdf->SetXY($x + $w_aset[0] + $w_aset[1] + $w_aset[2] + $w_aset[3] + $w_aset[4] + $w_aset[5] + $w_aset[6] + $w_aset[7] + 1, $y + 2);
        $pdf->MultiCell($w_aset[8] - 2, 4, $asal, 0, 'L');

        $pdf->SetXY($x + $w_aset[0] + $w_aset[1] + $w_aset[2] + $w_aset[3] + $w_aset[4] + $w_aset[5] + $w_aset[6] + $w_aset[7] + $w_aset[8] + 1, $y + 2);
        $pdf->MultiCell($w_aset[9] - 2, 4, $harga, 0, 'R');

        $pdf->SetXY($x + $w_aset[0] + $w_aset[1] + $w_aset[2] + $w_aset[3] + $w_aset[4] + $w_aset[5] + $w_aset[6] + $w_aset[7] + $w_aset[8] + $w_aset[9], $y + 2);
        $pdf->Cell($w_aset[10], 4, $umur, 0, 0, 'C');

        $pdf->SetXY($x + $w_aset[0] + $w_aset[1] + $w_aset[2] + $w_aset[3] + $w_aset[4] + $w_aset[5] + $w_aset[6] + $w_aset[7] + $w_aset[8] + $w_aset[9] + $w_aset[10], $y + 2);
        $pdf->Cell($w_aset[11], 4, $tgl, 0, 0, 'C');

        $imgPath = __DIR__ . '/../assets/dokumen/' . $r['dokumen'];
        $imgColStart = $x + $w_aset[0] + $w_aset[1] + $w_aset[2] + $w_aset[3] + $w_aset[4] + $w_aset[5] + $w_aset[6] + $w_aset[7] + $w_aset[8] + $w_aset[9] + $w_aset[10] + $w_aset[11];

        if (!empty($r['dokumen']) && file_exists($imgPath)) {
            $imgX = $imgColStart + (($w_aset[12] - 16) / 2);
            $imgY = $y + (($tinggi - 12) / 2);
            $pdf->Image($imgPath, $imgX, $imgY, 16, 12);
        } else {
            $pdf->SetXY($imgColStart, $y + 2);
            $pdf->Cell($w_aset[12], $tinggi - 4, 'Tidak Ada', 0, 0, 'C');
        }
        $pdf->SetY($y + $tinggi);
    }
}
$pdf->Ln(10);


// =========================================================================
// SECTION 2: LAPORAN KERUSAKAN (UPDATE DARI KERUSAKAN_CETAK.PHP)
// =========================================================================
$w_rusak = [8, 33, 18, 23, 18, 22, 22, 18, 25, 90];
$header_rusak = ['No', 'Nama Aset', 'Info Stok', 'Lokasi', 'Kategori', 'Pelapor', 'Teknisi', 'Tanggal', 'Status', 'Rincian Kerusakan'];

function cetakHeaderLaporanKerusakan($pdf, $w, $header)
{
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(72, 201, 176);
    $pdf->SetTextColor(255);
    for ($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 9, $header[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0);
}

if ($pdf->GetY() + 25 > 185) $pdf->AddPage();
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, '2. LAPORAN KERUSAKAN', 0, 1, 'L');

$pdf->SetFont('Arial', 'I', 8);
$pdf->SetTextColor(80, 80, 80);
$pdf->Cell(0, 5, '*Keterangan Info Stok: ( T = Tersedia | R = Rusak )', 0, 1, 'L');
$pdf->SetTextColor(0);

cetakHeaderLaporanKerusakan($pdf, $w_rusak, $header_rusak);

$where_rusak = [];
if (!empty($tahun_filter)) $where_rusak[] = "YEAR(kerusakan.tanggal) = '$tahun_filter'";
$whereSQL_rusak = count($where_rusak) ? 'WHERE ' . implode(' AND ', $where_rusak) : '';

$sql_rusak = "SELECT kerusakan.*, aset.lokasi, aset.kategori_aset, aset.stok_tersedia, aset.stok_rusak FROM kerusakan 
              LEFT JOIN aset ON kerusakan.nama_aset = aset.nama_aset COLLATE utf8mb4_general_ci
              $whereSQL_rusak ORDER BY aset.kategori_aset ASC, kerusakan.id DESC";
$res_rusak = mysqli_query($koneksi, $sql_rusak);

if (mysqli_num_rows($res_rusak) == 0) {
    $pdf->Cell(277, 8, 'Tidak ada data', 1, 1, 'C');
} else {
    $no_rusak = 1;
    while ($r = mysqli_fetch_assoc($res_rusak)) {
        $nama_aset = $r['nama_aset'] ?? '-';
        $lokasi    = !empty($r['lokasi']) ? $r['lokasi'] : '-';
        $kategori  = !empty($r['kategori_aset']) ? $r['kategori_aset'] : '-';
        $pelapor   = !empty($r['pelapor']) ? $r['pelapor'] : '-';
        $teknisi   = !empty($r['teknisi']) ? $r['teknisi'] : '-';
        $tanggal   = date('d/m/y', strtotime($r['tanggal']));
        $status    = !empty($r['status']) ? $r['status'] : '-';
        $ket       = $r['keterangan'] ?? '-';

        $stok_tersedia = isset($r['stok_tersedia']) ? $r['stok_tersedia'] : '0';
        $stok_rusak    = isset($r['stok_rusak']) ? $r['stok_rusak'] : '0';
        $info_stok     = "T: " . $stok_tersedia . " | R: " . $stok_rusak;

        // Mempertahankan MultiCell agar layout rapi apabila data terlalu panjang
        $maxLine = max(2, ceil(strlen($nama_aset) / 17), ceil(strlen($lokasi) / 11), ceil(strlen($ket) / 62));
        $tinggi = ($maxLine * 5) + 4;

        if ($pdf->GetY() + $tinggi > 185) {
            $pdf->AddPage();
            cetakHeaderLaporanKerusakan($pdf, $w_rusak, $header_rusak);
        }

        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $currX = $x;
        for ($i = 0; $i < count($w_rusak); $i++) {
            $pdf->Rect($currX, $y, $w_rusak[$i], $tinggi);
            $currX += $w_rusak[$i];
        }

        $pdf->SetXY($x, $y + 2);
        $pdf->Cell($w_rusak[0], 5, $no_rusak++, 0, 0, 'C');
        $pdf->SetXY($x + $w_rusak[0] + 1, $y + 2);
        $pdf->MultiCell($w_rusak[1] - 2, 5, $nama_aset, 0, 'L');
        $pdf->SetXY($x + $w_rusak[0] + $w_rusak[1], $y + 2);
        $pdf->Cell($w_rusak[2], 5, $info_stok, 0, 0, 'C');
        $pdf->SetXY($x + $w_rusak[0] + $w_rusak[1] + $w_rusak[2] + 1, $y + 2);
        $pdf->MultiCell($w_rusak[3] - 2, 5, $lokasi, 0, 'L');
        $pdf->SetXY($x + $w_rusak[0] + $w_rusak[1] + $w_rusak[2] + $w_rusak[3], $y + 2);
        $pdf->Cell($w_rusak[4], 5, $kategori, 0, 0, 'C');
        $pdf->SetXY($x + $w_rusak[0] + $w_rusak[1] + $w_rusak[2] + $w_rusak[3] + $w_rusak[4] + 1, $y + 2);
        $pdf->MultiCell($w_rusak[5] - 2, 5, $pelapor, 0, 'L');
        $pdf->SetXY($x + $w_rusak[0] + $w_rusak[1] + $w_rusak[2] + $w_rusak[3] + $w_rusak[4] + $w_rusak[5] + 1, $y + 2);
        $pdf->MultiCell($w_rusak[6] - 2, 5, $teknisi, 0, 'L');
        $pdf->SetXY($x + $w_rusak[0] + $w_rusak[1] + $w_rusak[2] + $w_rusak[3] + $w_rusak[4] + $w_rusak[5] + $w_rusak[6], $y + 2);
        $pdf->Cell($w_rusak[7], 5, $tanggal, 0, 0, 'C');
        $pdf->SetXY($x + $w_rusak[0] + $w_rusak[1] + $w_rusak[2] + $w_rusak[3] + $w_rusak[4] + $w_rusak[5] + $w_rusak[6] + $w_rusak[7], $y + 2);
        $pdf->Cell($w_rusak[8], 5, $status, 0, 0, 'C');
        $pdf->SetXY($x + $w_rusak[0] + $w_rusak[1] + $w_rusak[2] + $w_rusak[3] + $w_rusak[4] + $w_rusak[5] + $w_rusak[6] + $w_rusak[7] + $w_rusak[8] + 1, $y + 2);
        $pdf->MultiCell($w_rusak[9] - 2, 5, $ket, 0, 'L');
        $pdf->SetY($y + $tinggi);
    }
}
$pdf->Ln(10);


// =========================================================================
// SECTION 3: LAPORAN PERAWATAN
// =========================================================================
$w_rawat = [10, 55, 30, 40, 50, 27, 35, 30];
$header_rawat = ['No', 'Nama Aset', 'Kategori', 'Lokasi Ruang', 'Teknisi Bertugas', 'Tgl Rawat', 'Jadwal Kalibrasi', 'Status'];

function cetakHeaderPerawatan($pdf, $w, $header)
{
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(72, 201, 176);
    $pdf->SetTextColor(255);
    for ($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 9, $header[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0);
}

if ($pdf->GetY() + 25 > 185) $pdf->AddPage();
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, '3. LAPORAN PERAWATAN', 0, 1, 'L');
cetakHeaderPerawatan($pdf, $w_rawat, $header_rawat);

$where_rawat = [];
if (!empty($tahun_filter)) $where_rawat[] = "YEAR(perawatan.tanggal) = '$tahun_filter'";
$whereSQL_rawat = count($where_rawat) ? 'WHERE ' . implode(' AND ', $where_rawat) : '';

$sql_rawat = "SELECT perawatan.*, aset.lokasi, aset.kategori_aset FROM perawatan 
              LEFT JOIN aset ON perawatan.nama_aset = aset.nama_aset COLLATE utf8mb4_general_ci
              $whereSQL_rawat ORDER BY perawatan.tanggal DESC";
$res_rawat = mysqli_query($koneksi, $sql_rawat);

if (mysqli_num_rows($res_rawat) == 0) {
    $pdf->Cell(277, 8, 'Tidak ada data', 1, 1, 'C');
} else {
    $no_rawat = 1;
    while ($r = mysqli_fetch_assoc($res_rawat)) {
        $nama_aset = $r['nama_aset'] ?? '-';
        $kategori  = $r['kategori_aset'] ?? '-';
        $lokasi    = $r['lokasi'] ?? '-';
        $teknisi   = $r['teknisi'] ?? '-';
        $tanggal   = date('d/m/Y', strtotime($r['tanggal']));
        $status    = $r['status'] ?? '-';

        $tgl_berikutnya = $r['tanggal_kalibrasi_berikutnya'] ?? '';
        if ($tgl_berikutnya && $tgl_berikutnya != '0000-00-00') {
            $kalibrasi_tgl = date('d/m/Y', strtotime($tgl_berikutnya));
            $selisih_detik = strtotime($tgl_berikutnya) - strtotime('today');
            $selisih_hari = floor($selisih_detik / (60 * 60 * 24));
            if ($selisih_hari <= 7 && $selisih_hari >= 0) $kalibrasi_status = "H-$selisih_hari";
            elseif ($selisih_hari < 0) $kalibrasi_status = "Lewat " . abs($selisih_hari) . " Hr";
            else $kalibrasi_status = "Aman";
            $kalibrasi_cetak = $kalibrasi_tgl . "\n[" . $kalibrasi_status . "]";
        } else {
            $kalibrasi_cetak = "-";
        }

        $maxLine = max(2, ceil(strlen($nama_aset) / 32), ceil(strlen($lokasi) / 24), ceil(strlen($teknisi) / 30));
        $tinggi = ($maxLine * 5) + 4;
        if ($pdf->GetY() + $tinggi > 185) {
            $pdf->AddPage();
            cetakHeaderPerawatan($pdf, $w_rawat, $header_rawat);
        }

        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $currX = $x;
        for ($i = 0; $i < count($w_rawat); $i++) {
            $pdf->Rect($currX, $y, $w_rawat[$i], $tinggi);
            $currX += $w_rawat[$i];
        }

        $pdf->SetXY($x, $y + 2);
        $pdf->Cell($w_rawat[0], 5, $no_rawat++, 0, 0, 'C');
        $pdf->SetXY($x + $w_rawat[0] + 1, $y + 2);
        $pdf->MultiCell($w_rawat[1] - 2, 5, $nama_aset, 0, 'L');
        $pdf->SetXY($x + $w_rawat[0] + $w_rawat[1] + 1, $y + 2);
        $pdf->MultiCell($w_rawat[2] - 2, 5, $kategori, 0, 'C');
        $pdf->SetXY($x + $w_rawat[0] + $w_rawat[1] + $w_rawat[2] + 1, $y + 2);
        $pdf->MultiCell($w_rawat[3] - 2, 5, $lokasi, 0, 'L');
        $pdf->SetXY($x + $w_rawat[0] + $w_rawat[1] + $w_rawat[2] + $w_rawat[3] + 1, $y + 2);
        $pdf->MultiCell($w_rawat[4] - 2, 5, $teknisi, 0, 'L');
        $pdf->SetXY($x + $w_rawat[0] + $w_rawat[1] + $w_rawat[2] + $w_rawat[3] + $w_rawat[4], $y + 2);
        $pdf->Cell($w_rawat[5], 5, $tanggal, 0, 0, 'C');
        $pdf->SetXY($x + $w_rawat[0] + $w_rawat[1] + $w_rawat[2] + $w_rawat[3] + $w_rawat[4] + $w_rawat[5] + 1, $y + 2);
        $pdf->MultiCell($w_rawat[6] - 2, 5, $kalibrasi_cetak, 0, 'C');
        $pdf->SetXY($x + $w_rawat[0] + $w_rawat[1] + $w_rawat[2] + $w_rawat[3] + $w_rawat[4] + $w_rawat[5] + $w_rawat[6], $y + 2);
        $pdf->Cell($w_rawat[7], 5, $status, 0, 0, 'C');
        $pdf->SetY($y + $tinggi);
    }
}
$pdf->Ln(10);


// =========================================================================
// SECTION 4: LAPORAN PERBAIKAN
// =========================================================================
$w_baik = [10, 35, 25, 20, 25, 25, 20, 25, 92];
$header_baik = ['No', 'Nama Aset', 'Lokasi', 'Kategori', 'Pelapor', 'Teknisi', 'Tanggal', 'Status', 'Keterangan'];

if ($pdf->GetY() + 25 > 185) $pdf->AddPage();
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, '4. LAPORAN PERBAIKAN', 0, 1, 'L');
cetakHeaderLaporanKerusakan($pdf, $w_baik, $header_baik);

$where_baik = ["(kerusakan.status = 'Dalam Perbaikan' OR kerusakan.status = 'Selesai Diperbaiki')"];
if (!empty($tahun_filter)) $where_baik[] = "YEAR(kerusakan.tanggal) = '$tahun_filter'";
$whereSQL_baik = "WHERE " . implode(' AND ', $where_baik);

$sql_baik = "SELECT kerusakan.*, aset.kategori_aset, aset.lokasi FROM kerusakan 
             LEFT JOIN aset ON kerusakan.nama_aset = aset.nama_aset COLLATE utf8mb4_general_ci
             $whereSQL_baik ORDER BY kerusakan.tanggal DESC";
$res_baik = mysqli_query($koneksi, $sql_baik);

if (mysqli_num_rows($res_baik) == 0) {
    $pdf->Cell(277, 8, 'Tidak ada data', 1, 1, 'C');
} else {
    $no_baik = 1;
    while ($r = mysqli_fetch_assoc($res_baik)) {
        $nama_aset  = $r['nama_aset'] ?? '-';
        $lokasi     = $r['lokasi'] ?? '-';
        $kategori   = $r['kategori_aset'] ?? '-';
        $pelapor    = $r['pelapor'] ?? '-';
        $teknisi    = $r['teknisi'] ?? '-';
        $tanggal    = date('d-m-Y', strtotime($r['tanggal']));
        $status     = $r['status'] ?? '-';
        $keterangan = $r['keterangan'] ?? '-';

        if (strlen($nama_aset) > 20) $nama_aset = substr($nama_aset, 0, 18) . '...';
        $maxLine = max(1, ceil(strlen($nama_aset) / 20), ceil(strlen($keterangan) / 60));
        $tinggi = ($maxLine * 5) + 4;

        if ($pdf->GetY() + $tinggi > 185) {
            $pdf->AddPage();
            cetakHeaderLaporanKerusakan($pdf, $w_baik, $header_baik);
        }

        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $currX = $x;
        foreach ($w_baik as $width) {
            $pdf->Rect($currX, $y, $width, $tinggi);
            $currX += $width;
        }

        $pdf->SetXY($x, $y + 2);
        $pdf->Cell($w_baik[0], 5, $no_baik++, 0, 0, 'C');
        $pdf->SetXY($x + $w_baik[0] + 1, $y + 2);
        $pdf->MultiCell($w_baik[1] - 2, 5, $nama_aset, 0, 'L');
        $pdf->SetXY($x + $w_baik[0] + $w_baik[1] + 1, $y + 2);
        $pdf->MultiCell($w_baik[2] - 2, 5, $lokasi, 0, 'L');
        $pdf->SetXY($x + $w_baik[0] + $w_baik[1] + $w_baik[2], $y + 2);
        $pdf->Cell($w_baik[3], 5, $kategori, 0, 0, 'C');
        $pdf->SetXY($x + $w_baik[0] + $w_baik[1] + $w_baik[2] + $w_baik[3] + 1, $y + 2);
        $pdf->MultiCell($w_baik[4] - 2, 5, $pelapor, 0, 'L');
        $pdf->SetXY($x + $w_baik[0] + $w_baik[1] + $w_baik[2] + $w_baik[3] + $w_baik[4] + 1, $y + 2);
        $pdf->MultiCell($w_baik[5] - 2, 5, $teknisi, 0, 'L');
        $pdf->SetXY($x + $w_baik[0] + $w_baik[1] + $w_baik[2] + $w_baik[3] + $w_baik[4] + $w_baik[5], $y + 2);
        $pdf->Cell($w_baik[6], 5, $tanggal, 0, 0, 'C');
        $pdf->SetXY($x + $w_baik[0] + $w_baik[1] + $w_baik[2] + $w_baik[3] + $w_baik[4] + $w_baik[5] + $w_baik[6], $y + 2);
        $pdf->Cell($w_baik[7], 5, $status, 0, 0, 'C');
        $pdf->SetXY($x + $w_baik[0] + $w_baik[1] + $w_baik[2] + $w_baik[3] + $w_baik[4] + $w_baik[5] + $w_baik[6] + $w_baik[7] + 1, $y + 2);
        $pdf->MultiCell($w_baik[8] - 2, 5, $keterangan, 0, 'L');
        $pdf->SetY($y + $tinggi);
    }
}
$pdf->Ln(10);


// =========================================================================
// SECTION 5: LAPORAN PERAWATAN BERJALAN
// =========================================================================
$w_pb = [10, 50, 30, 45, 50, 40, 52];
$header_pb = ['No', 'Nama Aset', 'Kategori', 'Lokasi Ruang', 'Teknisi Bertugas', 'Tanggal Mulai', 'Status Perawatan'];

function cetakHeaderPerawatanBerjalan($pdf, $w, $header)
{
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(72, 201, 176);
    $pdf->SetTextColor(255);
    for ($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 9, $header[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0);
}

if ($pdf->GetY() + 25 > 185) $pdf->AddPage();
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, '5. LAPORAN PERAWATAN BERJALAN', 0, 1, 'L');
cetakHeaderPerawatanBerjalan($pdf, $w_pb, $header_pb);

$where_pb = ["p.status IN ('Belum Dimulai','Sedang Proses')"];
if (!empty($tahun_filter)) $where_pb[] = "YEAR(p.tanggal) = '$tahun_filter'";
$whereSQL_pb = "WHERE " . implode(' AND ', $where_pb);

$sql_pb = "SELECT p.*, a.kategori_aset, a.lokasi FROM perawatan p 
           LEFT JOIN aset a ON p.nama_aset = a.nama_aset COLLATE utf8mb4_general_ci
           $whereSQL_pb ORDER BY p.tanggal DESC";
$res_pb = mysqli_query($koneksi, $sql_pb);

if (mysqli_num_rows($res_pb) == 0) {
    $pdf->Cell(277, 8, 'Tidak ada data', 1, 1, 'C');
} else {
    $no_pb = 1;
    while ($r = mysqli_fetch_assoc($res_pb)) {
        $nama_aset = $r['nama_aset'] ?? '-';
        $kategori  = $r['kategori_aset'] ?? '-';
        $lokasi    = $r['lokasi'] ?? '-';
        $teknisi   = $r['teknisi'] ?? '-';
        $tanggal   = date('d/m/Y', strtotime($r['tanggal']));
        $status    = $r['status'] ?? '-';

        $maxLine = max(2, ceil(strlen($nama_aset) / 25), ceil(strlen($lokasi) / 20), ceil(strlen($teknisi) / 25));
        $tinggi = ($maxLine * 5) + 4;

        if ($pdf->GetY() + $tinggi > 185) {
            $pdf->AddPage();
            cetakHeaderPerawatanBerjalan($pdf, $w_pb, $header_pb);
        }

        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $currX = $x;
        for ($i = 0; $i < count($w_pb); $i++) {
            $pdf->Rect($currX, $y, $w_pb[$i], $tinggi);
            $currX += $w_pb[$i];
        }

        $pdf->SetXY($x, $y + 2);
        $pdf->Cell($w_pb[0], 5, $no_pb++, 0, 0, 'C');
        $pdf->SetXY($x + $w_pb[0] + 1, $y + 2);
        $pdf->MultiCell($w_pb[1] - 2, 5, $nama_aset, 0, 'L');
        $pdf->SetXY($x + $w_pb[0] + $w_pb[1], $y + 2);
        $pdf->Cell($w_pb[2], 5, $kategori, 0, 0, 'C');
        $pdf->SetXY($x + $w_pb[0] + $w_pb[1] + $w_pb[2] + 1, $y + 2);
        $pdf->MultiCell($w_pb[3] - 2, 5, $lokasi, 0, 'L');
        $pdf->SetXY($x + $w_pb[0] + $w_pb[1] + $w_pb[2] + $w_pb[3] + 1, $y + 2);
        $pdf->MultiCell($w_pb[4] - 2, 5, $teknisi, 0, 'L');
        $pdf->SetXY($x + $w_pb[0] + $w_pb[1] + $w_pb[2] + $w_pb[3] + $w_pb[4], $y + 2);
        $pdf->Cell($w_pb[5], 5, $tanggal, 0, 0, 'C');
        $pdf->SetXY($x + $w_pb[0] + $w_pb[1] + $w_pb[2] + $w_pb[3] + $w_pb[4] + $w_pb[5], $y + 2);
        $pdf->Cell($w_pb[6], 5, $status, 0, 0, 'C');

        $pdf->SetY($y + $tinggi);
    }
}
$pdf->Ln(10);


// =========================================================================
// SECTION 6: LAPORAN PEMINJAMAN ASET
// =========================================================================
$w_pinjam = [10, 40, 74, 28, 45, 25, 25, 30];
$header_pinjam = ['No', 'Peminjam', 'Nama Alat', 'Kategori', 'Lokasi Asal', 'Tgl Pinjam', 'Tgl Kembali', 'Status'];

function cetakHeaderPeminjaman($pdf, $w, $header)
{
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(72, 201, 176);
    $pdf->SetTextColor(255);
    for ($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 9, $header[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0);
}

if ($pdf->GetY() + 25 > 185) $pdf->AddPage();
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, '6. LAPORAN PEMINJAMAN ASET', 0, 1, 'L');
cetakHeaderPeminjaman($pdf, $w_pinjam, $header_pinjam);

$where_pinjam = [];
if (!empty($tahun_filter)) $where_pinjam[] = "YEAR(peminjaman.tanggal_pinjam) = '$tahun_filter'";
$whereSQL_pinjam = count($where_pinjam) ? 'WHERE ' . implode(' AND ', $where_pinjam) : '';

$sql_pinjam = "SELECT peminjaman.*, aset.nama_aset, aset.kategori_aset, aset.lokasi 
               FROM peminjaman 
               JOIN aset ON peminjaman.id_aset = aset.id_aset 
               $whereSQL_pinjam ORDER BY peminjaman.id_pinjam DESC";
$res_pinjam = mysqli_query($koneksi, $sql_pinjam);

if (mysqli_num_rows($res_pinjam) == 0) {
    $pdf->Cell(277, 8, 'Tidak ada data', 1, 1, 'C');
} else {
    $no_pinjam = 1;
    while ($r = mysqli_fetch_assoc($res_pinjam)) {
        $peminjam_nama = $r['nama_peminjam'] ?? '-';
        $sumber_label = (isset($r['sumber']) && $r['sumber'] == 'App User') ? 'App User' : 'Admin';
        $peminjam = $peminjam_nama . "\n[" . $sumber_label . "]";

        $nama_aset = $r['nama_aset'] ?? '-';
        $kategori  = $r['kategori_aset'] ?? '-';
        $lokasi    = $r['lokasi'] ?? '-';
        $tgl_pinjam = (!empty($r['tanggal_pinjam']) && $r['tanggal_pinjam'] != '0000-00-00') ? date('d/m/Y', strtotime($r['tanggal_pinjam'])) : '-';
        $tgl_kembali = (!empty($r['tanggal_kembali']) && $r['tanggal_kembali'] != '0000-00-00') ? date('d/m/Y', strtotime($r['tanggal_kembali'])) : '-';
        $status = $r['status_pinjam'] ?? '-';

        $maxLine = max(2, ceil(strlen($nama_aset) / 40), ceil(strlen($kategori) / 18), ceil(strlen($lokasi) / 25));
        $tinggi = ($maxLine * 5) + 4;

        if ($pdf->GetY() + $tinggi > 185) {
            $pdf->AddPage();
            cetakHeaderPeminjaman($pdf, $w_pinjam, $header_pinjam);
        }

        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $currX = $x;
        for ($i = 0; $i < count($w_pinjam); $i++) {
            $pdf->Rect($currX, $y, $w_pinjam[$i], $tinggi);
            $currX += $w_pinjam[$i];
        }

        $pdf->SetXY($x, $y + 2);
        $pdf->Cell($w_pinjam[0], 5, $no_pinjam++, 0, 0, 'C');
        $pdf->SetXY($x + $w_pinjam[0] + 1, $y + 2);
        $pdf->MultiCell($w_pinjam[1] - 2, 5, $peminjam, 0, 'L');
        $pdf->SetXY($x + $w_pinjam[0] + $w_pinjam[1] + 1, $y + 2);
        $pdf->MultiCell($w_pinjam[2] - 2, 5, $nama_aset, 0, 'L');
        $pdf->SetXY($x + $w_pinjam[0] + $w_pinjam[1] + $w_pinjam[2] + 1, $y + 2);
        $pdf->MultiCell($w_pinjam[3] - 2, 5, $kategori, 0, 'C');
        $pdf->SetXY($x + $w_pinjam[0] + $w_pinjam[1] + $w_pinjam[2] + $w_pinjam[3] + 1, $y + 2);
        $pdf->MultiCell($w_pinjam[4] - 2, 5, $lokasi, 0, 'L');
        $pdf->SetXY($x + $w_pinjam[0] + $w_pinjam[1] + $w_pinjam[2] + $w_pinjam[3] + $w_pinjam[4], $y + 2);
        $pdf->Cell($w_pinjam[5], 5, $tgl_pinjam, 0, 0, 'C');
        $pdf->SetXY($x + $w_pinjam[0] + $w_pinjam[1] + $w_pinjam[2] + $w_pinjam[3] + $w_pinjam[4] + $w_pinjam[5], $y + 2);
        $pdf->Cell($w_pinjam[6], 5, $tgl_kembali, 0, 0, 'C');
        $pdf->SetXY($x + $w_pinjam[0] + $w_pinjam[1] + $w_pinjam[2] + $w_pinjam[3] + $w_pinjam[4] + $w_pinjam[5] + $w_pinjam[6], $y + 2);
        $pdf->Cell($w_pinjam[7], 5, $status, 0, 0, 'C');

        $pdf->SetY($y + $tinggi);
    }
}
$pdf->Ln(10);


// =========================================================================
// SECTION 7: LAPORAN HASIL AUDIT FISIK
// =========================================================================
$w_audit = [10, 38, 22, 25, 25, 20, 20, 25, 92];
$header_audit = ['No', 'Nama Aset', 'Kategori', 'Lokasi', 'Auditor', 'Tgl Audit', 'Kondisi', 'Bukti Fisik', 'Keterangan Tambahan'];

function cetakHeaderAudit($pdf, $w, $header)
{
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(72, 201, 176);
    $pdf->SetTextColor(255);
    for ($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 9, $header[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0);
}

if ($pdf->GetY() + 25 > 185) $pdf->AddPage();
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, '7. LAPORAN HASIL AUDIT FISIK', 0, 1, 'L');
cetakHeaderAudit($pdf, $w_audit, $header_audit);

$where_audit = [];
if (!empty($tahun_filter)) $where_audit[] = "YEAR(audit_fisik.tanggal_audit) = '$tahun_filter'";
$whereSQL_audit = count($where_audit) ? 'WHERE ' . implode(' AND ', $where_audit) : '';

$sql_audit = "SELECT audit_fisik.*, aset.nama_aset, aset.lokasi, aset.kategori_aset 
              FROM audit_fisik 
              INNER JOIN aset ON audit_fisik.id_aset = aset.id_aset 
              $whereSQL_audit ORDER BY audit_fisik.id_audit DESC";
$res_audit = mysqli_query($koneksi, $sql_audit);

if (mysqli_num_rows($res_audit) == 0) {
    $pdf->Cell(277, 8, 'Tidak ada data', 1, 1, 'C');
} else {
    $no_audit = 1;
    while ($r = mysqli_fetch_assoc($res_audit)) {
        $nama_aset = $r['nama_aset'] ?? '-';
        $kategori  = $r['kategori_aset'] ?? '-';
        $lokasi    = $r['lokasi'] ?? '-';
        $auditor   = $r['auditor'] ?? '-';
        $tanggal   = (!empty($r['tanggal_audit'])) ? date('d/m/Y', strtotime($r['tanggal_audit'])) : '-';
        $kondisi   = $r['kondisi_fisik'] ?? '-';
        $keterangan = $r['keterangan'] ?? '-';

        $maxLine = max(
            3,
            ceil(strlen($nama_aset) / 22),
            ceil(strlen($keterangan) / 60)
        );
        $tinggi = ($maxLine * 5) + 4;

        if ($pdf->GetY() + $tinggi > 185) {
            $pdf->AddPage();
            cetakHeaderAudit($pdf, $w_audit, $header_audit);
        }

        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $currX = $x;
        for ($i = 0; $i < count($w_audit); $i++) {
            $pdf->Rect($currX, $y, $w_audit[$i], $tinggi);
            $currX += $w_audit[$i];
        }

        $pdf->SetXY($x, $y + 2);
        $pdf->Cell($w_audit[0], 5, $no_audit++, 0, 0, 'C');

        $pdf->SetXY($x + $w_audit[0] + 1, $y + 2);
        $pdf->MultiCell($w_audit[1] - 2, 5, $nama_aset, 0, 'L');

        $pdf->SetXY($x + $w_audit[0] + $w_audit[1], $y + 2);
        $pdf->Cell($w_audit[2], 5, $kategori, 0, 0, 'C');

        $pdf->SetXY($x + $w_audit[0] + $w_audit[1] + $w_audit[2] + 1, $y + 2);
        $pdf->MultiCell($w_audit[3] - 2, 5, $lokasi, 0, 'L');

        $pdf->SetXY($x + $w_audit[0] + $w_audit[1] + $w_audit[2] + $w_audit[3] + 1, $y + 2);
        $pdf->MultiCell($w_audit[4] - 2, 5, $auditor, 0, 'L');

        $pdf->SetXY($x + $w_audit[0] + $w_audit[1] + $w_audit[2] + $w_audit[3] + $w_audit[4], $y + 2);
        $pdf->Cell($w_audit[5], 5, $tanggal, 0, 0, 'C');

        $pdf->SetXY($x + $w_audit[0] + $w_audit[1] + $w_audit[2] + $w_audit[3] + $w_audit[4] + $w_audit[5], $y + 2);
        $pdf->Cell($w_audit[6], 5, $kondisi, 0, 0, 'C');

        $imgPath = __DIR__ . '/../assets/img/' . ($r['gambar_rusak'] ?? '');
        $imgColStart = $x + $w_audit[0] + $w_audit[1] + $w_audit[2] + $w_audit[3] + $w_audit[4] + $w_audit[5] + $w_audit[6];

        if (!empty($r['gambar_rusak']) && file_exists($imgPath)) {
            $imgWidth = 18;
            $imgHeight = 14;
            $imgX = $imgColStart + (($w_audit[7] - $imgWidth) / 2);
            $imgY = $y + (($tinggi - $imgHeight) / 2);
            $pdf->Image($imgPath, $imgX, $imgY, $imgWidth, $imgHeight);
        } else {
            $pdf->SetXY($imgColStart, $y + 2);
            $pdf->Cell($w_audit[7], $tinggi - 4, '-', 0, 0, 'C');
        }

        $pdf->SetXY($x + $w_audit[0] + $w_audit[1] + $w_audit[2] + $w_audit[3] + $w_audit[4] + $w_audit[5] + $w_audit[6] + $w_audit[7] + 1, $y + 2);
        $pdf->MultiCell($w_audit[8] - 2, 5, $keterangan, 0, 'L');

        $pdf->SetY($y + $tinggi);
    }
}
$pdf->Ln(10);


// =========================================================================
// SECTION 8: LAPORAN REKAPITULASI NILAI ASET (UPDATE DARI LAPORAN_NILAI_CETAK.PHP)
// =========================================================================
$w_nilai = [10, 55, 25, 22, 15, 15, 40, 40, 55]; // Total: 277 mm
$header_nilai = ['No', 'Nama Aset', 'Kategori', 'Thn Masuk', 'Umur', 'Stok', 'Harga Total Awal', 'Susut / Tahun', 'Nilai Saat Ini'];

function cetakHeaderNilai($pdf, $w, $header)
{
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(72, 201, 176);
    $pdf->SetTextColor(255);
    for ($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 9, $header[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0);
}

if ($pdf->GetY() + 25 > 185) $pdf->AddPage();
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, '8. LAPORAN REKAPITULASI NILAI ASET', 0, 1, 'L');
cetakHeaderNilai($pdf, $w_nilai, $header_nilai);

$where_nilai = ["harga > 0"];
if (!empty($tahun_filter)) $where_nilai[] = "YEAR(tanggal_masuk) = '$tahun_filter'";
$whereSQL_nilai = "WHERE " . implode(' AND ', $where_nilai);

$sql_nilai = "SELECT * FROM aset $whereSQL_nilai ORDER BY tanggal_masuk DESC";
$res_nilai = mysqli_query($koneksi, $sql_nilai);

$total_harga_awal = 0;
$total_nilai_saat_ini = 0;
$tahun_sekarang = date('Y');

if (mysqli_num_rows($res_nilai) == 0) {
    $pdf->Cell(277, 8, 'Tidak ada data', 1, 1, 'C');
} else {
    $no_nilai = 1;
    while ($r = mysqli_fetch_assoc($res_nilai)) {
        $nama_aset = $r['nama_aset'];
        $kategori  = $r['kategori_aset'] ?? '-';

        // Logika Nilai yang dikali dengan total stok dari update laporan_nilai_cetak.php
        $stok      = (int)($r['total_stok'] ?? 0);
        $harga_total = $r['harga'] * $stok; // Hitung berdasarkan stok

        $umur      = isset($r['umur_ekonomis']) ? (int)$r['umur_ekonomis'] : 0;
        $tgl_masuk = $r['tanggal_masuk'];
        $thn_masuk = date('Y', strtotime($tgl_masuk));

        $susut_per_tahun = 0;
        $nilai_sekarang = $harga_total;

        if ($umur > 0) {
            $susut_per_tahun = $harga_total / $umur;
            $pakai = $tahun_sekarang - $thn_masuk;
            if ($pakai < 0) $pakai = 0;
            if ($pakai > $umur) $pakai = $umur;

            $akumulasi = $pakai * $susut_per_tahun;
            $nilai_sekarang = $harga_total - $akumulasi;
        }

        $total_harga_awal += $harga_total;
        $total_nilai_saat_ini += $nilai_sekarang;

        if (strlen($nama_aset) > 28) $nama_aset = substr($nama_aset, 0, 25) . '...';

        if ($pdf->GetY() > 180) {
            $pdf->AddPage();
            cetakHeaderNilai($pdf, $w_nilai, $header_nilai);
        }

        $pdf->Cell($w_nilai[0], 8, $no_nilai++, 1, 0, 'C');
        $pdf->Cell($w_nilai[1], 8, $nama_aset, 1, 0, 'L');
        $pdf->Cell($w_nilai[2], 8, $kategori, 1, 0, 'C');
        $pdf->Cell($w_nilai[3], 8, $thn_masuk, 1, 0, 'C');
        $pdf->Cell($w_nilai[4], 8, ($umur > 0 ? $umur . " Thn" : "-"), 1, 0, 'C');
        $pdf->Cell($w_nilai[5], 8, $stok, 1, 0, 'C');
        $pdf->Cell($w_nilai[6], 8, 'Rp ' . number_format($harga_total, 0, ',', '.'), 1, 0, 'R');
        $pdf->SetTextColor(220, 53, 69);
        $pdf->Cell($w_nilai[7], 8, '- Rp ' . number_format($susut_per_tahun, 0, ',', '.'), 1, 0, 'R');
        $pdf->SetTextColor(0);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell($w_nilai[8], 8, 'Rp ' . number_format($nilai_sekarang, 0, ',', '.'), 1, 1, 'R');
        $pdf->SetFont('Arial', '', 8);
    }
}

// Baris Total Akumulasi Nilai Aset
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell($w_nilai[0] + $w_nilai[1] + $w_nilai[2] + $w_nilai[3] + $w_nilai[4] + $w_nilai[5], 10, 'TOTAL KESELURUHAN NILAI INVESTASI ASET', 1, 0, 'R', true);
$pdf->Cell($w_nilai[6], 10, 'Rp ' . number_format($total_harga_awal, 0, ',', '.'), 1, 0, 'R', true);
$pdf->Cell($w_nilai[7], 10, '', 1, 0, 'C', true);
$pdf->SetTextColor(25, 135, 84);
$pdf->Cell($w_nilai[8], 10, 'Rp ' . number_format($total_nilai_saat_ini, 0, ',', '.'), 1, 1, 'R', true);
$pdf->SetTextColor(0);
$pdf->SetFont('Arial', '', 8);
$pdf->Ln(10);


// =========================================================================
// SECTION 9: LAPORAN JADWAL KALIBRASI ASET & ALAT MEDIS
// =========================================================================
$w_kalib = [10, 50, 45, 35, 25, 27, 27, 58];
$header_kalib = ['No', 'Nama Aset', 'Merk / Tipe', 'Lokasi Ruang', 'Kategori', 'Tgl Rawat', 'Tgl Kalibrasi', 'Status / Sisa Waktu'];

function cetakHeaderKalib($pdf, $w, $header)
{
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(72, 201, 176);
    $pdf->SetTextColor(255);
    for ($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 9, $header[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0);
}

if ($pdf->GetY() + 25 > 185) $pdf->AddPage();
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, '9. LAPORAN JADWAL KALIBRASI ASET & ALAT MEDIS', 0, 1, 'L');
cetakHeaderKalib($pdf, $w_kalib, $header_kalib);

$where_kalib = ["perawatan.tanggal_kalibrasi_berikutnya IS NOT NULL AND perawatan.tanggal_kalibrasi_berikutnya >= '2000-01-01'"];
if (!empty($tahun_filter)) $where_kalib[] = "YEAR(perawatan.tanggal_kalibrasi_berikutnya) = '$tahun_filter'";
$whereSQL_kalib = "WHERE " . implode(' AND ', $where_kalib);

$sql_kalib = "SELECT perawatan.*, aset.lokasi, aset.kategori_aset, aset.jenis, aset.tipe_aset 
              FROM perawatan 
              LEFT JOIN aset ON perawatan.nama_aset = aset.nama_aset COLLATE utf8mb4_general_ci
              $whereSQL_kalib 
              ORDER BY perawatan.tanggal_kalibrasi_berikutnya ASC";
$res_kalib = mysqli_query($koneksi, $sql_kalib);

if (mysqli_num_rows($res_kalib) == 0) {
    $pdf->Cell(277, 8, 'Tidak ada data jadwal kalibrasi.', 1, 1, 'C');
} else {
    $no_kalib = 1;
    while ($r = mysqli_fetch_assoc($res_kalib)) {
        $nama_aset = $r['nama_aset'] ?? '-';
        $merk_tipe = ($r['jenis'] ?? '-') . " / " . ($r['tipe_aset'] ?? '-');
        $lokasi    = $r['lokasi'] ?? '-';
        $kategori  = $r['kategori_aset'] ?? '-';
        $t_rawat   = (!empty($r['tanggal']) && $r['tanggal'] != '0000-00-00') ? date('d/m/Y', strtotime($r['tanggal'])) : '-';

        $tgl_berikutnya = $r['tanggal_kalibrasi_berikutnya'];
        $t_kalib = date('d/m/Y', strtotime($tgl_berikutnya));

        $selisih_hari = floor((strtotime($tgl_berikutnya) - strtotime('today')) / 86400);
        if ($selisih_hari <= 7 && $selisih_hari >= 0) {
            $status = "Mendekati H-" . $selisih_hari;
        } elseif ($selisih_hari < 0) {
            $status = "Terlewat " . abs($selisih_hari) . " Hari";
        } else {
            $status = "Aman (> 7 Hari)";
        }

        $maxLine = max(2, ceil(strlen($nama_aset) / 28), ceil(strlen($merk_tipe) / 22), ceil(strlen($lokasi) / 18));
        $tinggi = ($maxLine * 5) + 4;

        if ($pdf->GetY() + $tinggi > 185) {
            $pdf->AddPage();
            cetakHeaderKalib($pdf, $w_kalib, $header_kalib);
        }

        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $currX = $x;
        for ($i = 0; $i < count($w_kalib); $i++) {
            $pdf->Rect($currX, $y, $w_kalib[$i], $tinggi);
            $currX += $w_kalib[$i];
        }

        $pdf->SetXY($x, $y + 2);
        $pdf->Cell($w_kalib[0], 5, $no_kalib++, 0, 0, 'C');
        $pdf->SetXY($x + $w_kalib[0] + 1, $y + 2);
        $pdf->MultiCell($w_kalib[1] - 2, 5, $nama_aset, 0, 'L');
        $pdf->SetXY($x + $w_kalib[0] + $w_kalib[1] + 1, $y + 2);
        $pdf->MultiCell($w_kalib[2] - 2, 5, $merk_tipe, 0, 'L');
        $pdf->SetXY($x + $w_kalib[0] + $w_kalib[1] + $w_kalib[2] + 1, $y + 2);
        $pdf->MultiCell($w_kalib[3] - 2, 5, $lokasi, 0, 'L');
        $pdf->SetXY($x + $w_kalib[0] + $w_kalib[1] + $w_kalib[2] + $w_kalib[3], $y + 2);
        $pdf->Cell($w_kalib[4], 5, $kategori, 0, 0, 'C');
        $pdf->SetXY($x + $w_kalib[0] + $w_kalib[1] + $w_kalib[2] + $w_kalib[3] + $w_kalib[4], $y + 2);
        $pdf->Cell($w_kalib[5], 5, $t_rawat, 0, 0, 'C');
        $pdf->SetXY($x + $w_kalib[0] + $w_kalib[1] + $w_kalib[2] + $w_kalib[3] + $w_kalib[4] + $w_kalib[5], $y + 2);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell($w_kalib[6], 5, $t_kalib, 0, 0, 'C');
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetXY($x + $w_kalib[0] + $w_kalib[1] + $w_kalib[2] + $w_kalib[3] + $w_kalib[4] + $w_kalib[5] + $w_kalib[6] + 1, $y + 2);
        $pdf->MultiCell($w_kalib[7] - 2, 5, $status, 0, 'C');

        $pdf->SetY($y + $tinggi);
    }
}
$pdf->Ln(10);


// =========================================================================
// SECTION 10: LAPORAN MUTASI & PELACAKAN LOKASI ASET
// =========================================================================
$w_pindah = [10, 22, 50, 25, 35, 35, 35, 65];
$header_pindah = ['No', 'Tanggal', 'Nama Aset', 'Kategori', 'P. Jawab', 'Lok. Awal', 'Lok. Baru', 'Keterangan'];

function cetakHeaderPelacakan($pdf, $w, $header)
{
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(72, 201, 176);
    $pdf->SetTextColor(255);
    for ($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 9, $header[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0);
}

if ($pdf->GetY() + 25 > 185) $pdf->AddPage();
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, '10. LAPORAN MUTASI & PELACAKAN LOKASI ASET', 0, 1, 'L');
cetakHeaderPelacakan($pdf, $w_pindah, $header_pindah);

$where_pindah = [];
if (!empty($tahun_filter)) $where_pindah[] = "YEAR(r.tanggal_pindah) = '$tahun_filter'";
$whereSQL_pindah = count($where_pindah) > 0 ? "WHERE " . implode(" AND ", $where_pindah) : "";

$sql_pindah = "SELECT r.*, a.nama_aset, a.kategori_aset 
               FROM riwayat_lokasi r 
               JOIN aset a ON r.id_aset = a.id_aset 
               $whereSQL_pindah 
               ORDER BY r.tanggal_pindah DESC, r.id_riwayat DESC";
$res_pindah = mysqli_query($koneksi, $sql_pindah);

if (mysqli_num_rows($res_pindah) == 0) {
    $pdf->Cell(277, 8, 'Tidak ada data pelacakan aset.', 1, 1, 'C');
} else {
    $no_pindah = 1;
    while ($row = mysqli_fetch_assoc($res_pindah)) {
        $tgl_pindah = ($row['tanggal_pindah'] && $row['tanggal_pindah'] != '0000-00-00') ? date('d-m-Y', strtotime($row['tanggal_pindah'])) : '-';
        $kat_aset = ($row['kategori_aset'] == 'Medis') ? 'Medis' : 'Non-Medis';
        $p_jawab = isset($row['penanggung_jawab']) ? $row['penanggung_jawab'] : '-';

        $maxLine = max(2, ceil(strlen($row['nama_aset']) / 25), ceil(strlen($p_jawab) / 20), ceil(strlen($row['keterangan']) / 32));
        $tinggi = ($maxLine * 5) + 4;

        if ($pdf->GetY() + $tinggi > 185) {
            $pdf->AddPage();
            cetakHeaderPelacakan($pdf, $w_pindah, $header_pindah);
        }

        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $currX = $x;
        for ($i = 0; $i < count($w_pindah); $i++) {
            $pdf->Rect($currX, $y, $w_pindah[$i], $tinggi);
            $currX += $w_pindah[$i];
        }

        $pdf->SetXY($x, $y + 2);
        $pdf->Cell($w_pindah[0], 5, $no_pindah++, 0, 0, 'C');
        $pdf->SetXY($x + $w_pindah[0] + 1, $y + 2);
        $pdf->Cell($w_pindah[1] - 2, 5, $tgl_pindah, 0, 0, 'C');
        $pdf->SetXY($x + $w_pindah[0] + $w_pindah[1] + 1, $y + 2);
        $pdf->MultiCell($w_pindah[2] - 2, 5, $row['nama_aset'], 0, 'L');
        $pdf->SetXY($x + $w_pindah[0] + $w_pindah[1] + $w_pindah[2] + 1, $y + 2);
        $pdf->Cell($w_pindah[3] - 2, 5, $kat_aset, 0, 0, 'C');
        $pdf->SetXY($x + $w_pindah[0] + $w_pindah[1] + $w_pindah[2] + $w_pindah[3] + 1, $y + 2);
        $pdf->MultiCell($w_pindah[4] - 2, 5, $p_jawab, 0, 'L');
        $pdf->SetXY($x + $w_pindah[0] + $w_pindah[1] + $w_pindah[2] + $w_pindah[3] + $w_pindah[4] + 1, $y + 2);
        $pdf->MultiCell($w_pindah[5] - 2, 5, $row['lokasi_sebelumnya'], 0, 'L');
        $pdf->SetXY($x + $w_pindah[0] + $w_pindah[1] + $w_pindah[2] + $w_pindah[3] + $w_pindah[4] + $w_pindah[5] + 1, $y + 2);
        $pdf->MultiCell($w_pindah[6] - 2, 5, $row['lokasi_baru'], 0, 'L');
        $pdf->SetXY($x + $w_pindah[0] + $w_pindah[1] + $w_pindah[2] + $w_pindah[3] + $w_pindah[4] + $w_pindah[5] + $w_pindah[6] + 1, $y + 2);
        $pdf->MultiCell($w_pindah[7] - 2, 5, $row['keterangan'], 0, 'L');

        $pdf->SetY($y + $tinggi);
    }
}
$pdf->Ln(10);


// =========================================================================
// SECTION 11: LAPORAN INFRASTRUKTUR (GEDUNG & KAMAR)
// =========================================================================
$w_infra = [8, 22, 52, 32, 45, 24, 22, 28, 44];
$header_infra = ['No', 'Kode', 'Nama Infrastruktur', 'Kategori', 'Gedung / Lt', 'Kapasitas', 'Luas', 'Kondisi', 'P. Jawab'];

function cetakHeaderInfraSemua($pdf, $w, $header)
{
    $pdf->SetFont('Arial', 'B', 7.5);
    $pdf->SetFillColor(72, 201, 176);
    $pdf->SetTextColor(255);
    for ($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 8, $header[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetTextColor(0);
}

if ($pdf->GetY() + 25 > 185) $pdf->AddPage();
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, '11. LAPORAN INFRASTRUKTUR (GEDUNG, RUANGAN & KAMAR)', 0, 1, 'L');
cetakHeaderInfraSemua($pdf, $w_infra, $header_infra);

$where_infra = [];
if (!empty($tahun_filter)) $where_infra[] = "YEAR(created_at) = '$tahun_filter'";
$whereSQL_infra = count($where_infra) > 0 ? "WHERE " . implode(" AND ", $where_infra) : "";

$sql_infra = "SELECT * FROM infrastruktur $whereSQL_infra ORDER BY id_infrastruktur DESC";
$res_infra = mysqli_query($koneksi, $sql_infra);

if (mysqli_num_rows($res_infra) == 0) {
    $pdf->Cell(277, 8, 'Tidak ada data infrastruktur.', 1, 1, 'C');
} else {
    $no_infra = 1;
    while ($r = mysqli_fetch_assoc($res_infra)) {
        $nama_infra = $r['nama_infrastruktur'] ?? '-';
        $kat_infra  = $r['kategori'] ?? '-';
        $gedung_lt  = ($r['nama_gedung'] ?? '-') . (!empty($r['lantai']) ? ' / Lt ' . $r['lantai'] : '');
        $kapasitas  = ($r['kapasitas'] ?? '0') . ' Orang';
        $luas       = ($r['luas_area'] ?? '-') . ' m2';
        $kondisi    = $r['kondisi'] ?? '-';
        $pj         = $r['penanggung_jawab'] ?? '-';

        $maxLine = max(2, ceil(strlen($nama_infra) / 28), ceil(strlen($gedung_lt) / 22), ceil(strlen($pj) / 22));
        $tinggi = ($maxLine * 4.5) + 3;

        if ($pdf->GetY() + $tinggi > 185) {
            $pdf->AddPage();
            cetakHeaderInfraSemua($pdf, $w_infra, $header_infra);
        }

        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $currX = $x;
        for ($i = 0; $i < count($w_infra); $i++) {
            $pdf->Rect($currX, $y, $w_infra[$i], $tinggi);
            $currX += $w_infra[$i];
        }

        $pdf->SetXY($x, $y + 1.5);
        $pdf->Cell($w_infra[0], 4, $no_infra++, 0, 0, 'C');
        $pdf->SetXY($x + $w_infra[0] + 1, $y + 1.5);
        $pdf->Cell($w_infra[1] - 2, 4, $r['kode_infrastruktur'] ?? '-', 0, 0, 'C');
        $pdf->SetXY($x + $w_infra[0] + $w_infra[1] + 1, $y + 1.5);
        $pdf->MultiCell($w_infra[2] - 2, 4, $nama_infra, 0, 'L');
        $pdf->SetXY($x + $w_infra[0] + $w_infra[1] + $w_infra[2] + 1, $y + 1.5);
        $pdf->Cell($w_infra[3] - 2, 4, $kat_infra, 0, 0, 'C');
        $pdf->SetXY($x + $w_infra[0] + $w_infra[1] + $w_infra[2] + $w_infra[3] + 1, $y + 1.5);
        $pdf->MultiCell($w_infra[4] - 2, 4, $gedung_lt, 0, 'L');
        $pdf->SetXY($x + $w_infra[0] + $w_infra[1] + $w_infra[2] + $w_infra[3] + $w_infra[4] + 1, $y + 1.5);
        $pdf->Cell($w_infra[5] - 2, 4, $kapasitas, 0, 0, 'C');
        $pdf->SetXY($x + $w_infra[0] + $w_infra[1] + $w_infra[2] + $w_infra[3] + $w_infra[4] + $w_infra[5] + 1, $y + 1.5);
        $pdf->Cell($w_infra[6] - 2, 4, $luas, 0, 0, 'C');
        $pdf->SetXY($x + $w_infra[0] + $w_infra[1] + $w_infra[2] + $w_infra[3] + $w_infra[4] + $w_infra[5] + $w_infra[6] + 1, $y + 1.5);
        $pdf->Cell($w_infra[7] - 2, 4, $kondisi, 0, 0, 'C');
        $pdf->SetXY($x + $w_infra[0] + $w_infra[1] + $w_infra[2] + $w_infra[3] + $w_infra[4] + $w_infra[5] + $w_infra[6] + $w_infra[7] + 1, $y + 1.5);
        $pdf->MultiCell($w_infra[8] - 2, 4, $pj, 0, 'L');

        $pdf->SetY($y + $tinggi);
    }
}
$pdf->Ln(6);


// =========================================================================
// SECTION 12: LAPORAN PENYUSUTAN NILAI ASET (PER PERIODE)
// =========================================================================
$w_susut = [8, 22, 55, 22, 35, 30, 15, 30, 30, 30];
$header_susut = ['No', 'Kode', 'Nama Aset', 'Kategori', 'Lokasi Ruangan', 'Perolehan (Rp)', 'Umur', 'Beban/Thn', 'Akumulasi', 'Nilai Buku'];

function cetakHeaderSusutSemua($pdf, $w, $header)
{
    $pdf->SetFont('Arial', 'B', 7.5);
    $pdf->SetFillColor(72, 201, 176);
    $pdf->SetTextColor(255);
    for ($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 8, $header[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetTextColor(0);
}

if ($pdf->GetY() + 25 > 185) $pdf->AddPage();
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, '12. LAPORAN PENYUSUTAN NILAI ASET (DEPRESIASI GARIS LURUS)', 0, 1, 'L');
cetakHeaderSusutSemua($pdf, $w_susut, $header_susut);

$where_susut = ["harga > 0"];
if (!empty($tahun_filter)) $where_susut[] = "YEAR(tanggal_masuk) <= '$tahun_filter'";
$whereSQL_susut = "WHERE " . implode(" AND ", $where_susut);

$sql_susut = "SELECT * FROM aset $whereSQL_susut ORDER BY id_aset DESC";
$res_susut = mysqli_query($koneksi, $sql_susut);

if (mysqli_num_rows($res_susut) == 0) {
    $pdf->Cell(277, 8, 'Tidak ada data penyusutan aset.', 1, 1, 'C');
} else {
    $no_susut = 1;
    $thnHitung = !empty($tahun_filter) ? $tahun_filter : (int)date('Y');
    while ($r = mysqli_fetch_assoc($res_susut)) {
        $umur = max(1, (int)($r['umur_ekonomis'] ?? 5));
        $thnMasuk = (int)date('Y', strtotime($r['tanggal_masuk'] ?? date('Y-m-d')));
        $umurPakai = max(0, $thnHitung - $thnMasuk);
        $harga = (float)$r['harga'];
        $bebanThn = $harga / $umur;
        $akum = min($harga, $bebanThn * $umurPakai);
        $nilaiBuku = max(0, $harga - $akum);

        $maxLine = max(2, ceil(strlen($r['nama_aset']) / 30), ceil(strlen($r['lokasi']) / 20));
        $tinggi = ($maxLine * 4.5) + 3;

        if ($pdf->GetY() + $tinggi > 185) {
            $pdf->AddPage();
            cetakHeaderSusutSemua($pdf, $w_susut, $header_susut);
        }

        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $currX = $x;
        for ($i = 0; $i < count($w_susut); $i++) {
            $pdf->Rect($currX, $y, $w_susut[$i], $tinggi);
            $currX += $w_susut[$i];
        }

        $pdf->SetXY($x, $y + 1.5);
        $pdf->Cell($w_susut[0], 4, $no_susut++, 0, 0, 'C');
        $pdf->SetXY($x + $w_susut[0] + 1, $y + 1.5);
        $pdf->Cell($w_susut[1] - 2, 4, $r['kode_aset'] ?? '-', 0, 0, 'C');
        $pdf->SetXY($x + $w_susut[0] + $w_susut[1] + 1, $y + 1.5);
        $pdf->MultiCell($w_susut[2] - 2, 4, $r['nama_aset'], 0, 'L');
        $pdf->SetXY($x + $w_susut[0] + $w_susut[1] + $w_susut[2] + 1, $y + 1.5);
        $pdf->Cell($w_susut[3] - 2, 4, $r['kategori_aset'], 0, 0, 'C');
        $pdf->SetXY($x + $w_susut[0] + $w_susut[1] + $w_susut[2] + $w_susut[3] + 1, $y + 1.5);
        $pdf->MultiCell($w_susut[4] - 2, 4, $r['lokasi'], 0, 'L');
        $pdf->SetXY($x + $w_susut[0] + $w_susut[1] + $w_susut[2] + $w_susut[3] + $w_susut[4] + 1, $y + 1.5);
        $pdf->Cell($w_susut[5] - 2, 4, 'Rp ' . number_format($harga, 0, ',', '.'), 0, 0, 'R');
        $pdf->SetXY($x + $w_susut[0] + $w_susut[1] + $w_susut[2] + $w_susut[3] + $w_susut[4] + $w_susut[5] + 1, $y + 1.5);
        $pdf->Cell($w_susut[6] - 2, 4, $umur . ' Th', 0, 0, 'C');
        $pdf->SetXY($x + $w_susut[0] + $w_susut[1] + $w_susut[2] + $w_susut[3] + $w_susut[4] + $w_susut[5] + $w_susut[6] + 1, $y + 1.5);
        $pdf->Cell($w_susut[7] - 2, 4, 'Rp ' . number_format($bebanThn, 0, ',', '.'), 0, 0, 'R');
        $pdf->SetXY($x + $w_susut[0] + $w_susut[1] + $w_susut[2] + $w_susut[3] + $w_susut[4] + $w_susut[5] + $w_susut[6] + $w_susut[7] + 1, $y + 1.5);
        $pdf->Cell($w_susut[8] - 2, 4, 'Rp ' . number_format($akum, 0, ',', '.'), 0, 0, 'R');
        $pdf->SetXY($x + $w_susut[0] + $w_susut[1] + $w_susut[2] + $w_susut[3] + $w_susut[4] + $w_susut[5] + $w_susut[6] + $w_susut[7] + $w_susut[8] + 1, $y + 1.5);
        $pdf->Cell($w_susut[9] - 2, 4, 'Rp ' . number_format($nilaiBuku, 0, ',', '.'), 0, 0, 'R');

        $pdf->SetY($y + $tinggi);
    }
}
$pdf->Ln(6);


// =========================================================================
// SECTION 13: LAPORAN HASIL STOCK OPNAME (SISTEM VS FISIK)
// =========================================================================
$w_so = [8, 22, 24, 55, 35, 20, 20, 18, 35, 40];
$header_so = ['No', 'Tgl Opname', 'Kode Opname', 'Nama Aset', 'Lokasi', 'Sistem', 'Fisik', 'Selisih', 'Kondisi Fisik', 'Petugas'];

function cetakHeaderSOSemua($pdf, $w, $header)
{
    $pdf->SetFont('Arial', 'B', 7.5);
    $pdf->SetFillColor(72, 201, 176);
    $pdf->SetTextColor(255);
    for ($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 8, $header[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetTextColor(0);
}

if ($pdf->GetY() + 25 > 185) $pdf->AddPage();
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, '13. LAPORAN HASIL STOCK OPNAME (SISTEM VS FISIK)', 0, 1, 'L');
cetakHeaderSOSemua($pdf, $w_so, $header_so);

$where_so = [];
if (!empty($tahun_filter)) $where_so[] = "YEAR(s.tanggal_opname) = '$tahun_filter'";
$whereSQL_so = count($where_so) > 0 ? "WHERE " . implode(" AND ", $where_so) : "";

$sql_so = "SELECT s.*, a.nama_aset, a.lokasi as lokasi_master 
           FROM stock_opname s 
           LEFT JOIN aset a ON s.id_aset = a.id_aset 
           $whereSQL_so 
           ORDER BY s.id_opname DESC";
$res_so = mysqli_query($koneksi, $sql_so);

if (mysqli_num_rows($res_so) == 0) {
    $pdf->Cell(277, 8, 'Tidak ada data stock opname aset.', 1, 1, 'C');
} else {
    $no_so = 1;
    while ($r = mysqli_fetch_assoc($res_so)) {
        $tgl_so = ($r['tanggal_opname'] && $r['tanggal_opname'] != '0000-00-00') ? date('d-m-Y', strtotime($r['tanggal_opname'])) : '-';
        $nama_aset = $r['nama_aset'] ?? '-';
        $lokasi = !empty($r['lokasi_opname']) ? $r['lokasi_opname'] : ($r['lokasi_master'] ?? '-');
        $petugas = $r['petugas_opname'] ?? '-';

        $maxLine = max(2, ceil(strlen($nama_aset) / 28), ceil(strlen($lokasi) / 20), ceil(strlen($petugas) / 20));
        $tinggi = ($maxLine * 4.5) + 3;

        if ($pdf->GetY() + $tinggi > 185) {
            $pdf->AddPage();
            cetakHeaderSOSemua($pdf, $w_so, $header_so);
        }

        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $currX = $x;
        for ($i = 0; $i < count($w_so); $i++) {
            $pdf->Rect($currX, $y, $w_so[$i], $tinggi);
            $currX += $w_so[$i];
        }

        $pdf->SetXY($x, $y + 1.5);
        $pdf->Cell($w_so[0], 4, $no_so++, 0, 0, 'C');
        $pdf->SetXY($x + $w_so[0] + 1, $y + 1.5);
        $pdf->Cell($w_so[1] - 2, 4, $tgl_so, 0, 0, 'C');
        $pdf->SetXY($x + $w_so[0] + $w_so[1] + 1, $y + 1.5);
        $pdf->Cell($w_so[2] - 2, 4, $r['kode_opname'] ?? '-', 0, 0, 'C');
        $pdf->SetXY($x + $w_so[0] + $w_so[1] + $w_so[2] + 1, $y + 1.5);
        $pdf->MultiCell($w_so[3] - 2, 4, $nama_aset, 0, 'L');
        $pdf->SetXY($x + $w_so[0] + $w_so[1] + $w_so[2] + $w_so[3] + 1, $y + 1.5);
        $pdf->MultiCell($w_so[4] - 2, 4, $lokasi, 0, 'L');
        $pdf->SetXY($x + $w_so[0] + $w_so[1] + $w_so[2] + $w_so[3] + $w_so[4] + 1, $y + 1.5);
        $pdf->Cell($w_so[5] - 2, 4, $r['stok_sistem'] ?? '0', 0, 0, 'C');
        $pdf->SetXY($x + $w_so[0] + $w_so[1] + $w_so[2] + $w_so[3] + $w_so[4] + $w_so[5] + 1, $y + 1.5);
        $pdf->Cell($w_so[6] - 2, 4, $r['stok_fisik'] ?? '0', 0, 0, 'C');
        $pdf->SetXY($x + $w_so[0] + $w_so[1] + $w_so[2] + $w_so[3] + $w_so[4] + $w_so[5] + $w_so[6] + 1, $y + 1.5);
        $pdf->Cell($w_so[7] - 2, 4, $r['selisih'] ?? '0', 0, 0, 'C');
        $pdf->SetXY($x + $w_so[0] + $w_so[1] + $w_so[2] + $w_so[3] + $w_so[4] + $w_so[5] + $w_so[6] + $w_so[7] + 1, $y + 1.5);
        $pdf->Cell($w_so[8] - 2, 4, $r['kondisi_fisik'] ?? 'Sesuai', 0, 0, 'C');
        $pdf->SetXY($x + $w_so[0] + $w_so[1] + $w_so[2] + $w_so[3] + $w_so[4] + $w_so[5] + $w_so[6] + $w_so[7] + $w_so[8] + 1, $y + 1.5);
        $pdf->MultiCell($w_so[9] - 2, 4, $petugas, 0, 'L');

        $pdf->SetY($y + $tinggi);
    }
}
$pdf->Ln(6);


// =========================================================================
// SECTION 14: KARTU INVENTARIS RUANGAN (KIR) & SEBARAN ASET
// =========================================================================
$w_kir = [8, 60, 25, 25, 25, 25, 25, 24, 60];
$header_kir = ['No', 'Nama Ruangan / Unit Kerja', 'Jenis Aset', 'Total Unit', 'Kondisi Baik', 'Rusak', 'Servis', 'Keandalan', 'Total Nilai Aset (Rp)'];

function cetakHeaderKIRSemua($pdf, $w, $header)
{
    $pdf->SetFont('Arial', 'B', 7.5);
    $pdf->SetFillColor(72, 201, 176);
    $pdf->SetTextColor(255);
    for ($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 8, $header[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetTextColor(0);
}

if ($pdf->GetY() + 25 > 185) $pdf->AddPage();
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, '14. KARTU INVENTARIS RUANGAN (KIR) & REKAP SEBARAN ASET', 0, 1, 'L');
cetakHeaderKIRSemua($pdf, $w_kir, $header_kir);

$sql_kir = "SELECT 
    lokasi, 
    COUNT(*) as total_jenis,
    SUM(total_stok) as total_unit,
    SUM(stok_tersedia) as unit_baik,
    SUM(stok_rusak) as unit_rusak,
    SUM(stok_perawatan) as unit_rawat,
    SUM(harga * total_stok) as total_nilai
FROM aset 
WHERE lokasi IS NOT NULL AND lokasi != ''
GROUP BY lokasi 
ORDER BY total_nilai DESC, lokasi ASC";
$res_kir = mysqli_query($koneksi, $sql_kir);

if (mysqli_num_rows($res_kir) == 0) {
    $pdf->Cell(277, 8, 'Tidak ada data sebaran ruangan.', 1, 1, 'C');
} else {
    $no_kir = 1;
    while ($r = mysqli_fetch_assoc($res_kir)) {
        $totUnit = (int)$r['total_unit'];
        $uBaik = (int)$r['unit_baik'];
        $persen = $totUnit > 0 ? round(($uBaik / $totUnit) * 100, 1) . '%' : '100%';

        $maxLine = max(1, ceil(strlen($r['lokasi']) / 35));
        $tinggi = ($maxLine * 4.5) + 3;

        if ($pdf->GetY() + $tinggi > 185) {
            $pdf->AddPage();
            cetakHeaderKIRSemua($pdf, $w_kir, $header_kir);
        }

        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $currX = $x;
        for ($i = 0; $i < count($w_kir); $i++) {
            $pdf->Rect($currX, $y, $w_kir[$i], $tinggi);
            $currX += $w_kir[$i];
        }

        $pdf->SetXY($x, $y + 1.5);
        $pdf->Cell($w_kir[0], 4, $no_kir++, 0, 0, 'C');
        $pdf->SetXY($x + $w_kir[0] + 1, $y + 1.5);
        $pdf->MultiCell($w_kir[1] - 2, 4, $r['lokasi'], 0, 'L');
        $pdf->SetXY($x + $w_kir[0] + $w_kir[1] + 1, $y + 1.5);
        $pdf->Cell($w_kir[2] - 2, 4, $r['total_jenis'] . ' Item', 0, 0, 'C');
        $pdf->SetXY($x + $w_kir[0] + $w_kir[1] + $w_kir[2] + 1, $y + 1.5);
        $pdf->Cell($w_kir[3] - 2, 4, $totUnit . ' Unit', 0, 0, 'C');
        $pdf->SetXY($x + $w_kir[0] + $w_kir[1] + $w_kir[2] + $w_kir[3] + 1, $y + 1.5);
        $pdf->Cell($w_kir[4] - 2, 4, $uBaik . ' Unit', 0, 0, 'C');
        $pdf->SetXY($x + $w_kir[0] + $w_kir[1] + $w_kir[2] + $w_kir[3] + $w_kir[4] + 1, $y + 1.5);
        $pdf->Cell($w_kir[5] - 2, 4, ($r['unit_rusak'] ?? 0) . ' Unit', 0, 0, 'C');
        $pdf->SetXY($x + $w_kir[0] + $w_kir[1] + $w_kir[2] + $w_kir[3] + $w_kir[4] + $w_kir[5] + 1, $y + 1.5);
        $pdf->Cell($w_kir[6] - 2, 4, ($r['unit_rawat'] ?? 0) . ' Unit', 0, 0, 'C');
        $pdf->SetXY($x + $w_kir[0] + $w_kir[1] + $w_kir[2] + $w_kir[3] + $w_kir[4] + $w_kir[5] + 1, $y + 1.5);
        $pdf->Cell($w_kir[7] - 2, 4, $persen, 0, 0, 'C');
        $pdf->SetXY($x + $w_kir[0] + $w_kir[1] + $w_kir[2] + $w_kir[3] + $w_kir[4] + $w_kir[5] + $w_kir[6] + $w_kir[7] + 1, $y + 1.5);
        $pdf->Cell($w_kir[8] - 2, 4, 'Rp ' . number_format((float)$r['total_nilai'], 0, ',', '.'), 0, 0, 'R');

        $pdf->SetY($y + $tinggi);
    }
}
$pdf->Ln(6);


// =========================================================================
// SECTION 15: LAPORAN ANALISIS ASET & REKOMENDASI ANGGARAN (DSS)
// =========================================================================
$w_dss = [8, 25, 60, 45, 20, 25, 40, 54];
$header_dss = ['No', 'Kode Aset', 'Nama Barang / Alkes', 'Lokasi Ruangan', 'Thn Masuk', 'Sisa Umur', 'Estimasi Anggaran', 'Rekomendasi Keputusan'];

function cetakHeaderDSSSemua($pdf, $w, $header)
{
    $pdf->SetFont('Arial', 'B', 7.5);
    $pdf->SetFillColor(72, 201, 176);
    $pdf->SetTextColor(255);
    for ($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 8, $header[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetTextColor(0);
}

if ($pdf->GetY() + 25 > 185) $pdf->AddPage();
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, '15. LAPORAN ANALISIS ASET & REKOMENDASI ANGGARAN (CAPEX & OPEX DSS)', 0, 1, 'L');
cetakHeaderDSSSemua($pdf, $w_dss, $header_dss);

$thnSekarang = !empty($tahun_filter) ? $tahun_filter : (int)date('Y');
$sql_dss = "SELECT 
    kode_aset, nama_aset, lokasi, harga, total_stok, stok_rusak, umur_ekonomis, 
    YEAR(tanggal_masuk) as thn_masuk 
FROM aset 
WHERE harga > 0 
ORDER BY lokasi ASC, nama_aset ASC";
$res_dss = mysqli_query($koneksi, $sql_dss);

$no_dss = 1;
$found_dss = false;
while ($a = mysqli_fetch_assoc($res_dss)) {
    $thnMasuk = (int)$a['thn_masuk'];
    $umurPakai = max(0, $thnSekarang - $thnMasuk);
    $umurEko = max(1, (int)$a['umur_ekonomis']);
    $sisaUmur = $umurEko - $umurPakai;
    $nilaiItem = (float)$a['harga'] * (int)$a['total_stok'];

    if ($sisaUmur <= 1 || (int)$a['stok_rusak'] > 0) {
        $found_dss = true;
        $rekom = ($sisaUmur <= 0 || (int)$a['stok_rusak'] > 0) ? 'Prioritas 1 (Usulkan Ganti CAPEX)' : 'Prioritas 2 (Servis / Siaga OPEX)';
        $sisaStr = ($sisaUmur <= 0) ? 'Habis (' . $umurPakai . ' Th)' : $sisaUmur . ' Th Sisa';

        $maxLine = max(2, ceil(strlen($a['nama_aset']) / 30), ceil(strlen($a['lokasi']) / 24));
        $tinggi = ($maxLine * 4.5) + 3;

        if ($pdf->GetY() + $tinggi > 185) {
            $pdf->AddPage();
            cetakHeaderDSSSemua($pdf, $w_dss, $header_dss);
        }

        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $currX = $x;
        for ($i = 0; $i < count($w_dss); $i++) {
            $pdf->Rect($currX, $y, $w_dss[$i], $tinggi);
            $currX += $w_dss[$i];
        }

        $pdf->SetXY($x, $y + 1.5);
        $pdf->Cell($w_dss[0], 4, $no_dss++, 0, 0, 'C');
        $pdf->SetXY($x + $w_dss[0] + 1, $y + 1.5);
        $pdf->Cell($w_dss[1] - 2, 4, $a['kode_aset'], 0, 0, 'C');
        $pdf->SetXY($x + $w_dss[0] + $w_dss[1] + 1, $y + 1.5);
        $pdf->MultiCell($w_dss[2] - 2, 4, $a['nama_aset'], 0, 'L');
        $pdf->SetXY($x + $w_dss[0] + $w_dss[1] + $w_dss[2] + 1, $y + 1.5);
        $pdf->MultiCell($w_dss[3] - 2, 4, $a['lokasi'], 0, 'L');
        $pdf->SetXY($x + $w_dss[0] + $w_dss[1] + $w_dss[2] + $w_dss[3] + 1, $y + 1.5);
        $pdf->Cell($w_dss[4] - 2, 4, $thnMasuk, 0, 0, 'C');
        $pdf->SetXY($x + $w_dss[0] + $w_dss[1] + $w_dss[2] + $w_dss[3] + $w_dss[4] + 1, $y + 1.5);
        $pdf->Cell($w_dss[5] - 2, 4, $sisaStr, 0, 0, 'C');
        $pdf->SetXY($x + $w_dss[0] + $w_dss[1] + $w_dss[2] + $w_dss[3] + $w_dss[4] + $w_dss[5] + 1, $y + 1.5);
        $pdf->Cell($w_dss[6] - 2, 4, 'Rp ' . number_format($nilaiItem, 0, ',', '.'), 0, 0, 'R');
        $pdf->SetXY($x + $w_dss[0] + $w_dss[1] + $w_dss[2] + $w_dss[3] + $w_dss[4] + $w_dss[5] + $w_dss[6] + 1, $y + 1.5);
        $pdf->MultiCell($w_dss[7] - 2, 4, $rekom, 0, 'L');

        $pdf->SetY($y + $tinggi);
    }
}
if (!$found_dss) {
    $pdf->Cell(277, 8, 'Semua aset dalam batas umur ekonomis aman dan tidak ada usulan penggantian kritis.', 1, 1, 'C');
}
$pdf->Ln(6);


// =========================================================================
// SECTION 16: LAPORAN PRIORITAS & KLASIFIKASI PEMELIHARAAN
// =========================================================================
$w_prio = [8, 25, 60, 45, 25, 30, 35, 49];
$header_prio = ['No', 'Kode Aset', 'Nama Alkes / Fasilitas', 'Lokasi / Ruangan', 'Stok (T/R/P)', 'Prioritas', 'Standar Respon SLA', 'Nilai Investasi'];

function cetakHeaderPrioSemua($pdf, $w, $header)
{
    $pdf->SetFont('Arial', 'B', 7.5);
    $pdf->SetFillColor(72, 201, 176);
    $pdf->SetTextColor(255);
    for ($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 8, $header[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetTextColor(0);
}

if ($pdf->GetY() + 25 > 185) $pdf->AddPage();
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, '16. LAPORAN PRIORITAS & KLASIFIKASI PEMELIHARAAN ASET KRITIS', 0, 1, 'L');
cetakHeaderPrioSemua($pdf, $w_prio, $header_prio);

$sql_prio = "SELECT 
    kode_aset, nama_aset, lokasi, total_stok, stok_tersedia, stok_rusak, stok_perawatan, 
    tingkat_prioritas, harga 
FROM aset 
ORDER BY FIELD(tingkat_prioritas, 'Kritis', 'Penting', 'Rutin'), nama_aset ASC";
$res_prio = mysqli_query($koneksi, $sql_prio);

if (mysqli_num_rows($res_prio) == 0) {
    $pdf->Cell(277, 8, 'Tidak ada data prioritas pemeliharaan.', 1, 1, 'C');
} else {
    $no_prio = 1;
    while ($r = mysqli_fetch_assoc($res_prio)) {
        $prio = $r['tingkat_prioritas'] ?? 'Penting';
        $sla = ($prio === 'Kritis') ? '< 2 Jam (Seketika)' : (($prio === 'Rutin') ? '1 - 3 Hari Kerja' : 'Maks. 24 Jam');
        $stokStr = $r['total_stok'] . ' / ' . ($r['stok_rusak'] ?? 0) . ' / ' . ($r['stok_perawatan'] ?? 0);
        $totNilai = (float)$r['harga'] * (int)$r['total_stok'];

        $maxLine = max(2, ceil(strlen($r['nama_aset']) / 30), ceil(strlen($r['lokasi']) / 24));
        $tinggi = ($maxLine * 4.5) + 3;

        if ($pdf->GetY() + $tinggi > 185) {
            $pdf->AddPage();
            cetakHeaderPrioSemua($pdf, $w_prio, $header_prio);
        }

        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $currX = $x;
        for ($i = 0; $i < count($w_prio); $i++) {
            $pdf->Rect($currX, $y, $w_prio[$i], $tinggi);
            $currX += $w_prio[$i];
        }

        $pdf->SetXY($x, $y + 1.5);
        $pdf->Cell($w_prio[0], 4, $no_prio++, 0, 0, 'C');
        $pdf->SetXY($x + $w_prio[0] + 1, $y + 1.5);
        $pdf->Cell($w_prio[1] - 2, 4, $r['kode_aset'], 0, 0, 'C');
        $pdf->SetXY($x + $w_prio[0] + $w_prio[1] + 1, $y + 1.5);
        $pdf->MultiCell($w_prio[2] - 2, 4, $r['nama_aset'], 0, 'L');
        $pdf->SetXY($x + $w_prio[0] + $w_prio[1] + $w_prio[2] + 1, $y + 1.5);
        $pdf->MultiCell($w_prio[3] - 2, 4, $r['lokasi'], 0, 'L');
        $pdf->SetXY($x + $w_prio[0] + $w_prio[1] + $w_prio[2] + $w_prio[3] + 1, $y + 1.5);
        $pdf->Cell($w_prio[4] - 2, 4, $stokStr, 0, 0, 'C');
        $pdf->SetXY($x + $w_prio[0] + $w_prio[1] + $w_prio[2] + $w_prio[3] + $w_prio[4] + 1, $y + 1.5);
        $pdf->Cell($w_prio[5] - 2, 4, $prio, 0, 0, 'C');
        $pdf->SetXY($x + $w_prio[0] + $w_prio[1] + $w_prio[2] + $w_prio[3] + $w_prio[4] + $w_prio[5] + 1, $y + 1.5);
        $pdf->Cell($w_prio[6] - 2, 4, $sla, 0, 0, 'C');
        $pdf->SetXY($x + $w_prio[0] + $w_prio[1] + $w_prio[2] + $w_prio[3] + $w_prio[4] + $w_prio[5] + $w_prio[6] + 1, $y + 1.5);
        $pdf->Cell($w_prio[7] - 2, 4, 'Rp ' . number_format($totNilai, 0, ',', '.'), 0, 0, 'R');

        $pdf->SetY($y + $tinggi);
    }
}
$pdf->Ln(6);


// =========================================================================
// SECTION 17: LAPORAN MONITORING KONDISI & PEMELIHARAAN BERKALA
// =========================================================================
$w_mon = [8, 24, 55, 45, 28, 32, 50, 35];
$header_mon = ['No', 'Kode Aset', 'Nama Alkes / Peralatan', 'Lokasi & PJ Ruangan', 'Kondisi Fisik', 'Jadwal Servis', 'Riwayat & Tindakan', 'Teknisi / Status'];

function cetakHeaderMonSemua($pdf, $w, $header)
{
    $pdf->SetFont('Arial', 'B', 7.5);
    $pdf->SetFillColor(72, 201, 176);
    $pdf->SetTextColor(255);
    for ($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 8, $header[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetTextColor(0);
}

if ($pdf->GetY() + 25 > 185) $pdf->AddPage();
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, '17. LAPORAN MONITORING KONDISI & PEMELIHARAAN BERKALA', 0, 1, 'L');
cetakHeaderMonSemua($pdf, $w_mon, $header_mon);

$sql_mon = "SELECT 
    a.kode_aset, a.nama_aset, a.lokasi, a.pj_ruangan, a.stok_rusak, a.stok_perawatan, 
    a.jadwal_maintenance_berikutnya,
    (SELECT k.tindakan_perbaikan FROM kerusakan k WHERE TRIM(k.nama_aset) COLLATE utf8mb4_general_ci = TRIM(a.nama_aset) COLLATE utf8mb4_general_ci ORDER BY k.id DESC LIMIT 1) as tindakan_perbaikan,
    (SELECT p.teknisi FROM perawatan p WHERE TRIM(p.nama_aset) COLLATE utf8mb4_general_ci = TRIM(a.nama_aset) COLLATE utf8mb4_general_ci ORDER BY p.id DESC LIMIT 1) as teknisi_rawat
FROM aset a
ORDER BY a.id_aset DESC";
$res_mon = mysqli_query($koneksi, $sql_mon);

if (mysqli_num_rows($res_mon) == 0) {
    $pdf->Cell(277, 8, 'Tidak ada data monitoring pemeliharaan.', 1, 1, 'C');
} else {
    $no_mon = 1;
    while ($r = mysqli_fetch_assoc($res_mon)) {
        $lokasiPj = $r['lokasi'] . (!empty($r['pj_ruangan']) ? ' (' . $r['pj_ruangan'] . ')' : '');
        $kondisiStr = ($r['stok_rusak'] > 0) ? 'Rusak (' . $r['stok_rusak'] . ')' : (($r['stok_perawatan'] > 0) ? 'Servis (' . $r['stok_perawatan'] . ')' : 'Siap Pakai');
        $jdw = ($r['jadwal_maintenance_berikutnya'] && $r['jadwal_maintenance_berikutnya'] != '0000-00-00') ? date('d-m-Y', strtotime($r['jadwal_maintenance_berikutnya'])) : 'Rutin Berkala';
        $tindakan = !empty($r['tindakan_perbaikan']) ? $r['tindakan_perbaikan'] : 'Pengecekan fungsi berkala';
        $tek = !empty($r['teknisi_rawat']) ? $r['teknisi_rawat'] : 'Internal Sarpras';

        $maxLine = max(2, ceil(strlen($r['nama_aset']) / 28), ceil(strlen($lokasiPj) / 24), ceil(strlen($tindakan) / 26));
        $tinggi = ($maxLine * 4.5) + 3;

        if ($pdf->GetY() + $tinggi > 185) {
            $pdf->AddPage();
            cetakHeaderMonSemua($pdf, $w_mon, $header_mon);
        }

        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $currX = $x;
        for ($i = 0; $i < count($w_mon); $i++) {
            $pdf->Rect($currX, $y, $w_mon[$i], $tinggi);
            $currX += $w_mon[$i];
        }

        $pdf->SetXY($x, $y + 1.5);
        $pdf->Cell($w_mon[0], 4, $no_mon++, 0, 0, 'C');
        $pdf->SetXY($x + $w_mon[0] + 1, $y + 1.5);
        $pdf->Cell($w_mon[1] - 2, 4, $r['kode_aset'], 0, 0, 'C');
        $pdf->SetXY($x + $w_mon[0] + $w_mon[1] + 1, $y + 1.5);
        $pdf->MultiCell($w_mon[2] - 2, 4, $r['nama_aset'], 0, 'L');
        $pdf->SetXY($x + $w_mon[0] + $w_mon[1] + $w_mon[2] + 1, $y + 1.5);
        $pdf->MultiCell($w_mon[3] - 2, 4, $lokasiPj, 0, 'L');
        $pdf->SetXY($x + $w_mon[0] + $w_mon[1] + $w_mon[2] + $w_mon[3] + 1, $y + 1.5);
        $pdf->Cell($w_mon[4] - 2, 4, $kondisiStr, 0, 0, 'C');
        $pdf->SetXY($x + $w_mon[0] + $w_mon[1] + $w_mon[2] + $w_mon[3] + $w_mon[4] + 1, $y + 1.5);
        $pdf->Cell($w_mon[5] - 2, 4, $jdw, 0, 0, 'C');
        $pdf->SetXY($x + $w_mon[0] + $w_mon[1] + $w_mon[2] + $w_mon[3] + $w_mon[4] + $w_mon[5] + 1, $y + 1.5);
        $pdf->MultiCell($w_mon[6] - 2, 4, $tindakan, 0, 'L');
        $pdf->SetXY($x + $w_mon[0] + $w_mon[1] + $w_mon[2] + $w_mon[3] + $w_mon[4] + $w_mon[5] + $w_mon[6] + 1, $y + 1.5);
        $pdf->Cell($w_mon[7] - 2, 4, $tek, 0, 0, 'C');

        $pdf->SetY($y + $tinggi);
    }
}
$pdf->Ln(10);

// ==================== TANDA TANGAN RESMI (2 KOLOM PENANGGUNG JAWAB & KEPALA RS) ====================
renderReportSignature($pdf, $koneksi);

$pdf->Output('I', 'Rekap_Semua_Laporan_Pimpinan_' . date('Ymd_His') . '.pdf');
exit;
