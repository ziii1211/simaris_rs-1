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

// 1. Statistik Kritikalitas
$qStatAset = mysqli_query($koneksi, "SELECT 
    COUNT(*) as total_aset,
    SUM(tingkat_prioritas = 'Kritis') as total_kritis,
    SUM(tingkat_prioritas = 'Penting') as total_penting,
    SUM(tingkat_prioritas = 'Rutin') as total_rutin,
    SUM(CASE WHEN tingkat_prioritas = 'Kritis' THEN stok_tersedia ELSE 0 END) as kritis_tersedia,
    SUM(CASE WHEN tingkat_prioritas = 'Kritis' THEN total_stok ELSE 0 END) as kritis_total_unit,
    SUM(CASE WHEN tingkat_prioritas = 'Kritis' THEN (harga * total_stok) ELSE 0 END) as nilai_kritis
FROM aset");
$statAset = mysqli_fetch_assoc($qStatAset);

$totalKritisUnit = (int)$statAset['kritis_total_unit'];
$kritisTersedia  = (int)$statAset['kritis_tersedia'];
$readinessRate   = $totalKritisUnit > 0 ? round(($kritisTersedia / $totalKritisUnit) * 100, 1) : 100;

$qStatInfra = mysqli_query($koneksi, "SELECT 
    COUNT(*) as total_infra,
    SUM(tingkat_prioritas = 'Kritis') as infra_kritis,
    SUM(tingkat_prioritas = 'Penting') as infra_penting,
    SUM(tingkat_prioritas = 'Rutin') as infra_rutin
FROM infrastruktur");
$statInfra = mysqli_fetch_assoc($qStatInfra);

// Query Antrean Kritis
$qQueueCount = mysqli_query($koneksi, "SELECT COUNT(*) as jml FROM perawatan p 
    JOIN aset a ON TRIM(p.nama_aset) COLLATE utf8mb4_general_ci = TRIM(a.nama_aset) COLLATE utf8mb4_general_ci 
    WHERE a.tingkat_prioritas = 'Kritis' AND p.status != 'Selesai'");
$rQueueCount = mysqli_fetch_assoc($qQueueCount);
$antreanKritisAktif = (int)($rQueueCount['jml'] ?? 0);

// 2. Data Aset Berdasarkan Prioritas
$whereAset = [];
if (!empty($filter_prioritas)) {
    $whereAset[] = "tingkat_prioritas = '$filter_prioritas'";
}
if (!empty($filter_kategori)) {
    $whereAset[] = "kategori_aset = '$filter_kategori'";
}
$whereAsetSql = count($whereAset) > 0 ? "WHERE " . implode(" AND ", $whereAset) : "";
$qAset = mysqli_query($koneksi, "SELECT * FROM aset $whereAsetSql ORDER BY FIELD(tingkat_prioritas, 'Kritis', 'Penting', 'Rutin'), nama_aset ASC");

// 3. Data Infrastruktur Berdasarkan Prioritas
$whereInfra = [];
if (!empty($filter_prioritas)) {
    $whereInfra[] = "tingkat_prioritas = '$filter_prioritas'";
}
$whereInfraSql = count($whereInfra) > 0 ? "WHERE " . implode(" AND ", $whereInfra) : "";
$qInfra = mysqli_query($koneksi, "SELECT * FROM infrastruktur $whereInfraSql ORDER BY FIELD(tingkat_prioritas, 'Kritis', 'Penting', 'Rutin'), nama_infrastruktur ASC");

class PDF_Prioritas extends FPDF
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
        $this->Cell(150, 6, 'SIMARIS RS Bhayangkara Tk. III Banjarmasin - Laporan Prioritas & Klasifikasi Pemeliharaan', 0, 0, 'L');
        $this->Cell(0, 6, 'Halaman ' . $this->PageNo() . ' dari {nb}', 0, 0, 'R');
    }
}

$pdf = new PDF_Prioritas('L', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(15, 10, 15);
$pdf->SetAutoPageBreak(true, 18);

$pdf->AddPage();
$pdf->renderKopSurat();

// Judul Dokumen
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(0, 6, 'LAPORAN PRIORITAS DAN KLASIFIKASI PEMELIHARAAN ASET & INFRASTRUKTUR', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 9.5);
$pdf->SetTextColor(185, 28, 28);
$subJudul = 'STANDAR OPERASIONAL PROSEDUR PRIORITAS PEMELIHARAAN BERDASARKAN KRITIKALITAS PELAYANAN RS';
if (!empty($filter_prioritas)) {
    $subJudul .= ' (FILTER: TINGKAT ' . strtoupper($filter_prioritas) . ')';
}
$pdf->Cell(0, 5, $subJudul, 0, 1, 'C');
$pdf->Ln(4);

// Box Ringkasan Kesiapan & Prioritas
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetFillColor(241, 245, 249);
$pdf->SetTextColor(30, 41, 59);

$boxW = 66.75;
$pdf->Cell($boxW, 6, 'TOTAL ASET KRITIS (LIFE SUPPORT)', 1, 0, 'C', true);
$pdf->Cell($boxW, 6, 'TINGKAT KESIAPAN OPERASIONAL', 1, 0, 'C', true);
$pdf->Cell($boxW, 6, 'NILAI INVESTASI ASET KRITIS', 1, 0, 'C', true);
$pdf->Cell($boxW, 6, 'INFRASTRUKTUR KRITIS / ANTREAN', 1, 1, 'C', true);

$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(220, 38, 38);
$pdf->Cell($boxW, 7, $statAset['total_kritis'] . ' Jenis (' . $totalKritisUnit . ' Unit Alkes)', 1, 0, 'C');

$pdf->SetTextColor(13, 148, 136);
$pdf->Cell($boxW, 7, $readinessRate . '% Siap Pakai (' . $kritisTersedia . '/' . $totalKritisUnit . ' Unit)', 1, 0, 'C');

$pdf->SetTextColor(30, 41, 59);
$pdf->Cell($boxW, 7, 'Rp ' . number_format($statAset['nilai_kritis'], 0, ',', '.'), 1, 0, 'C');

$pdf->SetTextColor(217, 119, 6);
$pdf->Cell($boxW, 7, $statInfra['infra_kritis'] . ' Fasilitas | ' . $antreanKritisAktif . ' Perawatan Aktif', 1, 1, 'C');
$pdf->Ln(4);

// Tabel 1: Aset Berdasarkan Prioritas
$pdf->SetFont('Arial', 'B', 9.5);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(0, 6, '1. DAFTAR PERALATAN MEDIS & NON-MEDIS BERDASARKAN TINGKAT KRITIKALITAS', 0, 1, 'L');

$pdf->SetFont('Arial', 'B', 7.5);
$pdf->SetFillColor(72, 201, 176);
$pdf->SetTextColor(255);

$pdf->Cell(8, 7, 'NO', 1, 0, 'C', true);
$pdf->Cell(25, 7, 'KODE ASET', 1, 0, 'C', true);
$pdf->Cell(62, 7, 'NAMA BARANG / ALKES', 1, 0, 'C', true);
$pdf->Cell(45, 7, 'LOKASI / UNIT PELAYANAN', 1, 0, 'C', true);
$pdf->Cell(26, 7, 'STOK (T/B/R)', 1, 0, 'C', true);
$pdf->Cell(30, 7, 'TINGKAT PRIORITAS', 1, 0, 'C', true);
$pdf->Cell(35, 7, 'TARGET RESPON (SLA)', 1, 0, 'C', true);
$pdf->Cell(36, 7, 'NILAI TOTAL (RP)', 1, 1, 'C', true);
$pdf->SetTextColor(0);

$pdf->SetFont('Arial', '', 7.5);
$noA = 1;
while ($a = mysqli_fetch_assoc($qAset)) {
    // Determine SLA & Styling by Prioritas
    $prio = $a['tingkat_prioritas'] ?? 'Penting';
    $sla = 'Maks. 24 Jam';
    if ($prio === 'Kritis') {
        $sla = '< 2 Jam (Seketika)';
    } elseif ($prio === 'Rutin') {
        $sla = '1 - 3 Hari Kerja';
    }

    $nilaiTot = (float)$a['harga'] * (int)$a['total_stok'];
    $stokStr = $a['total_stok'] . ' / ' . $a['stok_tersedia'] . ' / ' . ($a['stok_rusak'] + $a['stok_perawatan']);

    // Highlight row if Kritis
    if ($prio === 'Kritis') {
        $pdf->SetFillColor(254, 242, 242);
        $fill = true;
    } else {
        $fill = false;
    }

    $pdf->Cell(8, 6, $noA++, 1, 0, 'C', $fill);
    $pdf->Cell(25, 6, $a['kode_aset'], 1, 0, 'C', $fill);
    $pdf->Cell(62, 6, substr($a['nama_aset'], 0, 36), 1, 0, 'L', $fill);
    $pdf->Cell(45, 6, substr($a['lokasi'], 0, 26), 1, 0, 'L', $fill);
    $pdf->Cell(26, 6, $stokStr, 1, 0, 'C', $fill);
    
    // Prioritas text
    if ($prio === 'Kritis') {
        $pdf->SetTextColor(220, 38, 38);
        $pdf->SetFont('Arial', 'B', 7.5);
    } elseif ($prio === 'Penting') {
        $pdf->SetTextColor(217, 119, 6);
        $pdf->SetFont('Arial', '', 7.5);
    } else {
        $pdf->SetTextColor(75, 85, 99);
        $pdf->SetFont('Arial', '', 7.5);
    }
    $pdf->Cell(30, 6, strtoupper($prio), 1, 0, 'C', $fill);
    
    $pdf->SetTextColor(30, 41, 59);
    $pdf->SetFont('Arial', '', 7.5);
    $pdf->Cell(35, 6, $sla, 1, 0, 'C', $fill);
    $pdf->Cell(36, 6, number_format($nilaiTot, 0, ',', '.'), 1, 1, 'R', $fill);
}

$pdf->Ln(4);

// Tabel 2: Infrastruktur Vital Rumah Sakit
$pdf->SetFont('Arial', 'B', 9.5);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(0, 6, '2. DAFTAR INFRASTRUKTUR VITAL PENUNJANG FASILITAS PELAYANAN RS', 0, 1, 'L');

$pdf->SetFont('Arial', 'B', 7.5);
$pdf->SetFillColor(72, 201, 176);
$pdf->SetTextColor(255);

$pdf->Cell(8, 7, 'NO', 1, 0, 'C', true);
$pdf->Cell(25, 7, 'KODE INFRA', 1, 0, 'C', true);
$pdf->Cell(70, 7, 'NAMA INFRASTRUKTUR / FASILITAS', 1, 0, 'C', true);
$pdf->Cell(45, 7, 'LOKASI / AREA', 1, 0, 'C', true);
$pdf->Cell(30, 7, 'KONDISI FISIK', 1, 0, 'C', true);
$pdf->Cell(30, 7, 'TINGKAT PRIORITAS', 1, 0, 'C', true);
$pdf->Cell(59, 7, 'STATUS OPERASIONAL & TINDAKAN', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 7.5);
$noI = 1;
while ($inf = mysqli_fetch_assoc($qInfra)) {
    $prioI = $inf['tingkat_prioritas'] ?? 'Penting';
    $fillI = ($prioI === 'Kritis');
    if ($fillI) {
        $pdf->SetFillColor(254, 242, 242);
    }

    $tindakan = ($inf['kondisi'] === 'Baik') ? 'Pemeliharaan Terjadwal Sesuai SOP' : 'Perlu Tindakan Servis / Kalibrasi';

    $pdf->Cell(8, 6, $noI++, 1, 0, 'C', $fillI);
    $pdf->Cell(25, 6, $inf['kode_infrastruktur'], 1, 0, 'C', $fillI);
    $pdf->Cell(70, 6, substr($inf['nama_infrastruktur'], 0, 40), 1, 0, 'L', $fillI);
    $locInf = trim(($inf['nama_gedung'] ?? '') . ' ' . ($inf['lantai'] ?? ''));
    $pdf->Cell(45, 6, substr($locInf, 0, 26), 1, 0, 'L', $fillI);
    $pdf->Cell(30, 6, $inf['kondisi'], 1, 0, 'C', $fillI);

    if ($prioI === 'Kritis') {
        $pdf->SetTextColor(220, 38, 38);
        $pdf->SetFont('Arial', 'B', 7.5);
    } else {
        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetFont('Arial', '', 7.5);
    }
    $pdf->Cell(30, 6, strtoupper($prioI), 1, 0, 'C', $fillI);

    $pdf->SetTextColor(30, 41, 59);
    $pdf->SetFont('Arial', '', 7.5);
    $pdf->Cell(59, 6, $tindakan, 1, 1, 'L', $fillI);
}

// Catatan Prosedur Penanganan Prioritas
$pdf->Ln(3);
$pdf->SetFont('Arial', 'I', 7.5);
$pdf->SetTextColor(75, 85, 99);
$pdf->MultiCell(0, 4, 'Kebijakan Standar Pelayanan: Aset Kritis (Life-Support & Emergency) mendapat hak prioritas antrean pertama pada modul Perawatan dan Pelaporan Kerusakan dengan SLA respon teknisi maksimal 2 jam. Seluruh teknisi elektromedis dan sarpras wajib mengutamakan kalibrasi preventif aset tingkat Kritis guna mencegah downtime pelayanan pasien.', 0, 'L');

// Tanda Tangan Resmi 2 Kolom
renderReportSignature($pdf, $koneksi, [
    'pj_jabatan' => 'Koordinator Sarpras & Elektromedis',
    'pj_nama'    => $_SESSION['nama_pengguna'] ?? 'Petugas Sarpras'
]);

$pdf->Output('I', 'Laporan_Prioritas_Pemeliharaan_' . date('Ymd_His') . '.pdf');
