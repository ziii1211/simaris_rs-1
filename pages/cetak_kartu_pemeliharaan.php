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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$aset = null;
if ($id > 0) {
    $qAset = mysqli_query($koneksi, "SELECT * FROM aset WHERE id_aset = $id LIMIT 1");
    $aset = mysqli_fetch_assoc($qAset);
}

if (!$aset) {
    $qFallback = mysqli_query($koneksi, "SELECT * FROM aset ORDER BY id_aset ASC LIMIT 1");
    $aset = mysqli_fetch_assoc($qFallback);
    if (!$aset) {
        die("Data aset tidak ditemukan dalam sistem.");
    }
}

$namaAsetTrim = trim($aset['nama_aset']);
$namaAsetEsc = mysqli_real_escape_string($koneksi, $namaAsetTrim);

// Riwayat Perawatan & Kalibrasi
$qPerawatan = mysqli_query($koneksi, "SELECT * FROM perawatan 
    WHERE TRIM(nama_aset) COLLATE utf8mb4_general_ci = '$namaAsetEsc' COLLATE utf8mb4_general_ci 
    ORDER BY tanggal DESC LIMIT 8");

// Riwayat Kerusakan & Tindakan Perbaikan
$qKerusakan = mysqli_query($koneksi, "SELECT * FROM kerusakan 
    WHERE TRIM(nama_aset) COLLATE utf8mb4_general_ci = '$namaAsetEsc' COLLATE utf8mb4_general_ci 
    ORDER BY tanggal DESC LIMIT 8");

class PDF_Kartu extends FPDF
{
    function renderKopSurat()
    {
        $logo1 = realpath(__DIR__ . '/../assets/img/logo_dokpol.png');
        $logo2 = realpath(__DIR__ . '/../assets/img/logo_rs.jpg');

        if ($logo1 && file_exists($logo1)) {
            $this->Image($logo1, 15, 10, 20);
        }
        if ($logo2 && file_exists($logo2)) {
            $this->Image($logo2, 175, 10, 20);
        }

        $this->SetY(10);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 5, 'KEPOLISIAN NEGARA REPUBLIK INDONESIA', 0, 1, 'C');
        $this->Cell(0, 5, 'DAERAH KALIMANTAN SELATAN / BIDDOKKES', 0, 1, 'C');
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 6, 'RUMAH SAKIT BHAYANGKARA TK. III BANJARMASIN', 0, 1, 'C');
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 4, 'Jl. A. Yani Km. 3.5 No. 477 Banjarmasin | Telp: (0511) 3254143', 0, 1, 'C');

        // Garis Ganda Kop
        $this->SetLineWidth(0.7);
        $this->Line(15, 36, 195, 36);
        $this->SetLineWidth(0.2);
        $this->Line(15, 37, 195, 37);
        $this->Ln(4);
    }

    function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 7.5);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(120, 6, 'SIMARIS RS Bhayangkara - Kartu Kendali Pemeliharaan & Riwayat Alkes', 0, 0, 'L');
        $this->Cell(0, 6, 'Halaman ' . $this->PageNo() . ' dari {nb}', 0, 0, 'R');
    }
}

$pdf = new PDF_Kartu('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(15, 10, 15);
$pdf->SetAutoPageBreak(true, 15);

$pdf->AddPage();
$pdf->renderKopSurat();

// Judul Kartu
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(0, 5, 'KARTU KENDALI & REKAM RIWAYAT PEMELIHARAAN ALAT', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetTextColor(13, 148, 136);
$pdf->Cell(0, 4, 'LEMBAR CATATAN HISTORIS SERVIS PREVENTIF & TINDAKAN PERBAIKAN ALAT', 0, 1, 'C');
$pdf->Ln(3);

// 1. Data Identitas Aset (Box 2 Kolom)
$pdf->SetFillColor(241, 245, 249);
$pdf->Rect(15, $pdf->GetY(), 180, 28, 'F');
$pdf->SetDrawColor(203, 213, 225);
$pdf->Rect(15, $pdf->GetY(), 180, 28, 'D');

$startY = $pdf->GetY() + 2;
$pdf->SetY($startY);

$pdf->SetFont('Arial', 'B', 8);
$pdf->SetTextColor(30, 41, 59);

// Kolom Kiri
$pdf->SetX(18);
$pdf->Cell(32, 5, 'KODE ASET / ALKES', 0, 0);
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetTextColor(2, 132, 199);
$pdf->Cell(60, 5, ': ' . $aset['kode_aset'], 0, 0);

// Kolom Kanan
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetTextColor(30, 41, 59);
$pdf->Cell(32, 5, 'LOKASI RUANGAN', 0, 0);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(50, 5, ': ' . $aset['lokasi'], 0, 1);

$pdf->SetX(18);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(32, 5, 'NAMA PERALATAN', 0, 0);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(60, 5, ': ' . substr($aset['nama_aset'], 0, 32), 0, 0);

$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(32, 5, 'PENANGGUNG JAWAB', 0, 0);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(50, 5, ': ' . substr($aset['pj_ruangan'] ?? 'Kepala Ruangan', 0, 26), 0, 1);

$pdf->SetX(18);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(32, 5, 'KATEGORI / TIPE', 0, 0);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(60, 5, ': ' . $aset['kategori_aset'] . ' / ' . ($aset['tipe_aset'] ?? '-'), 0, 0);

$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(32, 5, 'TINGKAT PRIORITAS', 0, 0);
$pdf->SetFont('Arial', 'B', 8);
if (($aset['tingkat_prioritas'] ?? '') === 'Kritis') {
    $pdf->SetTextColor(220, 38, 38);
    $pdf->Cell(50, 5, ': KRITIS (LIFE-SUPPORT)', 0, 1);
} else {
    $pdf->SetTextColor(30, 41, 59);
    $pdf->Cell(50, 5, ': ' . strtoupper($aset['tingkat_prioritas'] ?? 'Penting'), 0, 1);
}

$pdf->SetX(18);
$pdf->SetTextColor(30, 41, 59);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(32, 5, 'TANGGAL PENGADAAN', 0, 0);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(60, 5, ': ' . date('d-m-Y', strtotime($aset['tanggal_masuk'])), 0, 0);

$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(32, 5, 'JADWAL KALIBRASI', 0, 0);
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetTextColor(13, 148, 136);
$tglNext = !empty($aset['jadwal_maintenance_berikutnya']) ? date('d-m-Y', strtotime($aset['jadwal_maintenance_berikutnya'])) : '-';
$pdf->Cell(50, 5, ': ' . $tglNext, 0, 1);

$pdf->SetY($startY + 28);
$pdf->Ln(4);

// 2. Section Jadwal & Catatan Pemeliharaan Preventif
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(0, 5, '1. REKAM JEJAK PEMELIHARAAN PREVENTIF & KALIBRASI BERKALA', 0, 1, 'L');

$pdf->SetFont('Arial', 'B', 7);
$pdf->SetFillColor(240, 243, 246);
$pdf->SetTextColor(30, 41, 59);
$pdf->Cell(8, 6, 'NO', 1, 0, 'C', true);
$pdf->Cell(20, 6, 'TANGGAL', 1, 0, 'C', true);
$pdf->Cell(32, 6, 'JENIS MAINTENANCE', 1, 0, 'C', true);
$pdf->Cell(70, 6, 'LANGKAH / TINDAKAN PEMELIHARAAN', 1, 0, 'C', true);
$pdf->Cell(32, 6, 'TEKNISI & KALIBRASI', 1, 0, 'C', true);
$pdf->Cell(18, 6, 'STATUS', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 7);
if (mysqli_num_rows($qPerawatan) == 0) {
    $pdf->Cell(180, 6, 'Belum ada catatan pemeliharaan preventif/kalibrasi terjadwal.', 1, 1, 'C');
} else {
    $noP = 1;
    while ($p = mysqli_fetch_assoc($qPerawatan)) {
        $pdf->Cell(8, 5.5, $noP++, 1, 0, 'C');
        $pdf->Cell(20, 5.5, date('d-m-Y', strtotime($p['tanggal'])), 1, 0, 'C');
        $pdf->Cell(32, 5.5, substr($p['jenis_pemeliharaan'] ?? 'Preventif Rutin', 0, 20), 1, 0, 'L');
        $pdf->Cell(70, 5.5, substr($p['tindakan_pemeliharaan'] ?? 'Pembersihan filter & uji kelistrikan', 0, 48), 1, 0, 'L');
        $pdf->Cell(32, 5.5, substr($p['teknisi'] ?? '-', 0, 20), 1, 0, 'L');
        $pdf->Cell(18, 5.5, $p['status'] ?? 'Selesai', 1, 1, 'C');
    }
}
$pdf->Ln(4);

// 3. Section Riwayat Gangguan / Kerusakan & Tindakan Perbaikan
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(0, 5, '2. RIWAYAT GANGGUAN / KERUSAKAN & TINDAKAN PERBAIKAN TEKNISI', 0, 1, 'L');

$pdf->SetFont('Arial', 'B', 7);
$pdf->SetFillColor(240, 243, 246);
$pdf->SetTextColor(30, 41, 59);
$pdf->Cell(8, 6, 'NO', 1, 0, 'C', true);
$pdf->Cell(20, 6, 'TGL LAPOR', 1, 0, 'C', true);
$pdf->Cell(45, 6, 'KELUHAN & GEJALA RUSAK', 1, 0, 'C', true);
$pdf->Cell(62, 6, 'TINDAKAN PERBAIKAN & SPARE PART', 1, 0, 'C', true);
$pdf->Cell(27, 6, 'TEKNISI PIC', 1, 0, 'C', true);
$pdf->Cell(18, 6, 'BIAYA (RP)', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 7);
if (mysqli_num_rows($qKerusakan) == 0) {
    $pdf->Cell(180, 6, 'Tidak ada riwayat insiden kerusakan tercatat pada alat ini.', 1, 1, 'C');
} else {
    $noK = 1;
    while ($k = mysqli_fetch_assoc($qKerusakan)) {
        $tindakan = $k['tindakan_perbaikan'] ?? 'Pemeriksaan fungsi komponen';
        if (!empty($k['sparepart_diganti'])) {
            $tindakan .= ' (Part: ' . $k['sparepart_diganti'] . ')';
        }

        $pdf->Cell(8, 5.5, $noK++, 1, 0, 'C');
        $pdf->Cell(20, 5.5, date('d-m-Y', strtotime($k['tanggal'])), 1, 0, 'C');
        $pdf->Cell(45, 5.5, substr($k['keterangan'], 0, 28), 1, 0, 'L');
        $pdf->Cell(62, 5.5, substr($tindakan, 0, 42), 1, 0, 'L');
        $pdf->Cell(27, 5.5, substr($k['teknisi'] ?? 'Budi Setiawan', 0, 18), 1, 0, 'L');
        $pdf->Cell(18, 5.5, number_format($k['biaya_perbaikan'] ?? 0, 0, ',', '.'), 1, 1, 'R');
    }
}

// Tanda Tangan 2 Kolom (Teknisi Elektromedis & Kepala Ruangan)
$pdf->Ln(4);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(90, 5, 'Mengetahui / Penanggung Jawab Ruangan,', 0, 0, 'C');
$pdf->Cell(90, 5, 'Banjarmasin, ' . date('d F Y'), 0, 1, 'C');
$pdf->Cell(90, 5, 'Kepala Ruangan / Instalasi Pelayanan,', 0, 0, 'C');
$pdf->Cell(90, 5, 'Teknisi Elektromedis & Pemeliharaan,', 0, 1, 'C');
$pdf->Ln(18);

$pdf->SetFont('Arial', 'BU', 8.5);
$pdf->Cell(90, 5, $aset['pj_ruangan'] ?? 'Kepala Ruangan Pelayanan', 0, 0, 'C');
$pdf->Cell(90, 5, 'Budi Setiawan, A.Md.T.E.', 0, 1, 'C');

$pdf->SetFont('Arial', '', 7.5);
$pdf->Cell(90, 4, 'NIP/NRP. Penanggung Jawab Ruangan', 0, 0, 'C');
$pdf->Cell(90, 4, 'Teknisi Sarpras & Elektromedis Rumkit', 0, 1, 'C');

$pdf->Output('I', 'Kartu_Pemeliharaan_' . $aset['kode_aset'] . '.pdf');
