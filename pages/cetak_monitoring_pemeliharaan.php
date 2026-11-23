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

$filter_prioritas = isset($_GET['prioritas']) ? mysqli_real_escape_string($koneksi, $_GET['prioritas']) : '';
$filter_kategori  = isset($_GET['kategori']) ? mysqli_real_escape_string($koneksi, $_GET['kategori']) : '';
$search           = isset($_GET['search']) ? mysqli_real_escape_string($koneksi, trim($_GET['search'])) : '';

// 1. Statistik Ringkasan Eksekutif
$qStat = mysqli_query($koneksi, "SELECT 
    COUNT(*) as total_aset,
    SUM(stok_tersedia) as total_unit_tersedia,
    SUM(total_stok) as total_unit_keseluruhan,
    SUM(stok_rusak) as total_unit_rusak,
    SUM(stok_perawatan) as total_unit_perawatan,
    SUM(CASE WHEN tingkat_prioritas = 'Kritis' THEN 1 ELSE 0 END) as total_kritis,
    SUM(CASE WHEN DATEDIFF(jadwal_maintenance_berikutnya, CURDATE()) <= 7 AND DATEDIFF(jadwal_maintenance_berikutnya, CURDATE()) >= 0 THEN 1 ELSE 0 END) as jatuh_tempo_segera,
    SUM(CASE WHEN DATEDIFF(jadwal_maintenance_berikutnya, CURDATE()) < 0 THEN 1 ELSE 0 END) as terlewat_jadwal
FROM aset");
$stat = mysqli_fetch_assoc($qStat);

$totalUnitSemua = (int)($stat['total_unit_keseluruhan'] ?? 1);
$totalUnitBagus = (int)($stat['total_unit_tersedia'] ?? 0);
$healthIndex = $totalUnitSemua > 0 ? round(($totalUnitBagus / $totalUnitSemua) * 100, 1) : 100;

// Query Aset Terpantau
$where = [];
if (!empty($search)) {
    $where[] = "(a.nama_aset LIKE '%$search%' OR a.kode_aset LIKE '%$search%' OR a.lokasi LIKE '%$search%' OR a.pj_ruangan LIKE '%$search%')";
}
if (!empty($filter_kategori)) {
    $where[] = "a.kategori_aset = '$filter_kategori'";
}
if (!empty($filter_prioritas)) {
    $where[] = "a.tingkat_prioritas = '$filter_prioritas'";
}
$whereSql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

$qAset = mysqli_query($koneksi, "SELECT 
    a.*,
    (SELECT k.keterangan FROM kerusakan k WHERE TRIM(k.nama_aset) COLLATE utf8mb4_general_ci = TRIM(a.nama_aset) COLLATE utf8mb4_general_ci ORDER BY k.id DESC LIMIT 1) as keluhan_rusak,
    (SELECT k.tindakan_perbaikan FROM kerusakan k WHERE TRIM(k.nama_aset) COLLATE utf8mb4_general_ci = TRIM(a.nama_aset) COLLATE utf8mb4_general_ci ORDER BY k.id DESC LIMIT 1) as tindakan_perbaikan,
    (SELECT k.teknisi FROM kerusakan k WHERE TRIM(k.nama_aset) COLLATE utf8mb4_general_ci = TRIM(a.nama_aset) COLLATE utf8mb4_general_ci ORDER BY k.id DESC LIMIT 1) as teknisi_rusak,
    (SELECT p.teknisi FROM perawatan p WHERE TRIM(p.nama_aset) COLLATE utf8mb4_general_ci = TRIM(a.nama_aset) COLLATE utf8mb4_general_ci ORDER BY p.id DESC LIMIT 1) as teknisi_rawat,
    (SELECT p.tanggal_kalibrasi_berikutnya FROM perawatan p WHERE TRIM(p.nama_aset) COLLATE utf8mb4_general_ci = TRIM(a.nama_aset) COLLATE utf8mb4_general_ci ORDER BY p.id DESC LIMIT 1) as tgl_kalibrasi_p
FROM aset a
$whereSql
ORDER BY FIELD(a.tingkat_prioritas, 'Kritis', 'Penting', 'Rutin'), a.nama_aset ASC");

class PDF_Monitoring extends FPDF
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
        $this->Cell(150, 6, 'SIMARIS RS Bhayangkara Tk. III Banjarmasin - Laporan Monitoring Kondisi & Pemeliharaan Aset', 0, 0, 'L');
        $this->Cell(0, 6, 'Halaman ' . $this->PageNo() . ' dari {nb}', 0, 0, 'R');
    }
}

$pdf = new PDF_Monitoring('L', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(15, 10, 15);
$pdf->SetAutoPageBreak(true, 18);

$pdf->AddPage();
$pdf->renderKopSurat();

// Judul Dokumen
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(0, 6, 'LAPORAN MONITORING KONDISI FISIK DAN PEMELIHARAAN ASET BERKALA', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 9.5);
$pdf->SetTextColor(13, 148, 136);
$subJudul = 'PEMASTIAN KELAIKAN OPERASIONAL, JADWAL MAINTENANCE, TINDAKAN PERBAIKAN & PENANGGUNG JAWAB';
if (!empty($filter_prioritas)) {
    $subJudul .= ' - TINGKAT: ' . strtoupper($filter_prioritas);
}
$pdf->Cell(0, 5, $subJudul, 0, 1, 'C');
$pdf->Ln(4);

// Box Ringkasan Status Kesiapan
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetFillColor(241, 245, 249);
$pdf->SetTextColor(30, 41, 59);

$boxW = 66.75;
$pdf->Cell($boxW, 6, 'TOTAL ASET TERPANTAU BERKALA', 1, 0, 'C', true);
$pdf->Cell($boxW, 6, 'HEALTH INDEX / KESIAPAN FISIK', 1, 0, 'C', true);
$pdf->Cell($boxW, 6, 'MAINTENANCE JATUH TEMPO / OVERDUE', 1, 0, 'C', true);
$pdf->Cell($boxW, 6, 'STATUS GANGGUAN / DALAM PERBAIKAN', 1, 1, 'C', true);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell($boxW, 7, $stat['total_aset'] . ' Jenis (' . $stat['total_unit_keseluruhan'] . ' Unit Fisik)', 1, 0, 'C');

$pdf->SetTextColor(13, 148, 136);
$pdf->Cell($boxW, 7, $healthIndex . '% Siap Pakai (' . $stat['total_unit_tersedia'] . ' Unit)', 1, 0, 'C');

$pdf->SetTextColor(217, 119, 6);
$pdf->Cell($boxW, 7, $stat['jatuh_tempo_segera'] . ' Segera | ' . $stat['terlewat_jadwal'] . ' Terlewat', 1, 0, 'C');

$pdf->SetTextColor(220, 38, 38);
$pdf->Cell($boxW, 7, $stat['total_unit_rusak'] . ' Rusak | ' . $stat['total_unit_perawatan'] . ' Dalam Servis', 1, 1, 'C');
$pdf->Ln(4);

// Tabel Master Monitoring
$pdf->SetFont('Arial', 'B', 7.5);
$pdf->SetFillColor(72, 201, 176);
$pdf->SetTextColor(255);

$pdf->Cell(8, 7, 'NO', 1, 0, 'C', true);
$pdf->Cell(24, 7, 'KODE ASET', 1, 0, 'C', true);
$pdf->Cell(54, 7, 'NAMA ALAT / PERALATAN', 1, 0, 'C', true);
$pdf->Cell(44, 7, 'LOKASI & PJ RUANGAN', 1, 0, 'C', true);
$pdf->Cell(25, 7, 'KONDISI FISIK', 1, 0, 'C', true);
$pdf->Cell(25, 7, 'JADWAL SERV.', 1, 0, 'C', true);
$pdf->Cell(52, 7, 'RIWAYAT & TINDAKAN PERBAIKAN', 1, 0, 'C', true);
$pdf->Cell(35, 7, 'TEKNISI & KALIBRASI', 1, 1, 'C', true);
$pdf->SetTextColor(0);

$pdf->SetFont('Arial', '', 7);
$no = 1;
while ($a = mysqli_fetch_assoc($qAset)) {
    $prio = $a['tingkat_prioritas'] ?? 'Penting';
    $fill = ($prio === 'Kritis');
    if ($fill) {
        $pdf->SetFillColor(254, 242, 242);
    }

    $tglNext = $a['jadwal_maintenance_berikutnya'] ?: ($a['tgl_kalibrasi_p'] ?: '-');
    if ($tglNext != '-') {
        $tglNext = date('d-m-Y', strtotime($tglNext));
    }

    $kondisiStr = ($a['stok_rusak'] > 0) ? 'Rusak (' . $a['stok_rusak'] . ')' : (($a['stok_perawatan'] > 0) ? 'Servis (' . $a['stok_perawatan'] . ')' : '100% Baik');
    
    $tindakan = !empty($a['tindakan_perbaikan']) ? substr($a['tindakan_perbaikan'], 0, 36) : 'Pemeliharaan preventif terjadwal';
    $teknisi = !empty($a['teknisi_rawat']) ? $a['teknisi_rawat'] : (!empty($a['teknisi_rusak']) ? $a['teknisi_rusak'] : 'Budi Setiawan');

    $pdf->Cell(8, 6, $no++, 1, 0, 'C', $fill);
    $pdf->Cell(24, 6, $a['kode_aset'], 1, 0, 'C', $fill);
    
    // Nama Aset
    if ($prio === 'Kritis') {
        $pdf->SetTextColor(220, 38, 38);
        $pdf->SetFont('Arial', 'B', 7);
    } else {
        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetFont('Arial', '', 7);
    }
    $pdf->Cell(54, 6, substr($a['nama_aset'], 0, 34), 1, 0, 'L', $fill);

    $pdf->SetTextColor(30, 41, 59);
    $pdf->SetFont('Arial', '', 7);
    $pdf->Cell(44, 6, substr($a['lokasi'], 0, 24), 1, 0, 'L', $fill);

    // Kondisi
    if ($a['stok_rusak'] > 0) {
        $pdf->SetTextColor(220, 38, 38);
    } elseif ($a['stok_perawatan'] > 0) {
        $pdf->SetTextColor(217, 119, 6);
    } else {
        $pdf->SetTextColor(13, 148, 136);
    }
    $pdf->Cell(25, 6, $kondisiStr, 1, 0, 'C', $fill);

    $pdf->SetTextColor(30, 41, 59);
    $pdf->Cell(25, 6, $tglNext, 1, 0, 'C', $fill);
    $pdf->Cell(52, 6, $tindakan, 1, 0, 'L', $fill);
    $pdf->Cell(35, 6, substr($teknisi, 0, 22), 1, 1, 'L', $fill);
}

// Catatan Prosedur Monitoring
$pdf->Ln(3);
$pdf->SetFont('Arial', 'I', 7.5);
$pdf->SetTextColor(75, 85, 99);
$pdf->MultiCell(0, 4, 'Catatan Manajemen Mutu Sarpras: Monitoring kondisi fisik dan pengujian fungsi alkes dilakukan secara berkala. Setiap tindakan perbaikan dan penggantian suku cadang wajib dicatat pada logbook alat oleh teknisi elektromedis penanggung jawab guna memastikan kepatuhan akreditasi rumah sakit dan standar keselamatan pasien (patient safety).', 0, 'L');

// Tanda Tangan Resmi 2 Kolom
renderReportSignature($pdf, $koneksi, [
    'pj_jabatan' => 'Koordinator Elektromedis & Pemeliharaan Alkes',
    'pj_nama'    => $_SESSION['nama_pengguna'] ?? 'Petugas Elektromedis'
]);

$pdf->Output('I', 'Laporan_Monitoring_Pemeliharaan_' . date('Ymd_His') . '.pdf');
