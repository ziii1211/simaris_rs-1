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


$filter_ruangan = isset($_GET['ruangan']) ? trim($_GET['ruangan']) : '';
$semua = isset($_GET['semua']) ? (int)$_GET['semua'] : 0;

if (empty($filter_ruangan) && empty($semua)) {
    die("Pilih ruangan terlebih dahulu atau pilih cetak rekap semua ruangan.");
}

class PDF_KIR extends FPDF
{
    public $ruanganTitle = '';
    public $isFirstPage = true;

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

    function renderTableHeader()
    {
        $this->SetFont('Arial', 'B', 8.5);
        $this->SetFillColor(72, 201, 176);
        $this->SetTextColor(255);

        // Baris Header 1
        $this->Cell(8, 10, 'NO', 1, 0, 'C', true);
        $this->Cell(25, 10, 'KODE ASET', 1, 0, 'C', true);
        $this->Cell(62, 10, 'NAMA BARANG / ALAT', 1, 0, 'C', true);
        $this->Cell(45, 10, 'MERK / TIPE SPESIFIKASI', 1, 0, 'C', true);
        $this->Cell(22, 10, 'KATEGORI', 1, 0, 'C', true);
        $this->Cell(48, 5, 'RINCIAN KONDISI (UNIT)', 1, 0, 'C', true);
        $this->Cell(15, 10, 'TOTAL', 1, 0, 'C', true);
        $this->Cell(45, 10, 'KETERANGAN', 1, 1, 'C', true);

        // Sub Baris Header untuk Rincian Kondisi
        $this->SetXY(15 + 8 + 25 + 62 + 45 + 22, $this->GetY() - 5);
        $this->Cell(16, 5, 'BAIK', 1, 0, 'C', true);
        $this->Cell(16, 5, 'RUSAK', 1, 0, 'C', true);
        $this->Cell(16, 5, 'PERAWATAN', 1, 1, 'C', true);
        $this->SetTextColor(0);
    }

    function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(150, 6, 'SIMARIS RS Bhayangkara Tk. III Banjarmasin - Kartu Inventaris Ruangan (KIR)', 0, 0, 'L');
        $this->Cell(0, 6, 'Halaman ' . $this->PageNo() . ' dari {nb}', 0, 0, 'R');
    }
}

$pdf = new PDF_KIR('L', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(15, 10, 15);
$pdf->SetAutoPageBreak(true, 18);

// Jika Cetak Per Ruangan Spesifik
if (!empty($filter_ruangan)) {
    $pdf->AddPage();
    $pdf->renderKopSurat();

    // Judul Dokumen KIR
    $pdf->SetFont('Arial', 'B', 13);
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(0, 7, 'KARTU INVENTARIS RUANGAN (KIR)', 0, 1, 'C');
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(13, 148, 136);
    $pdf->Cell(0, 5, 'UNIT PELAYANAN / RUANGAN: ' . strtoupper($filter_ruangan), 0, 1, 'C');
    $pdf->Ln(4);

    // Info Ruangan Box
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(45, 5, 'Nama Rumah Sakit', 0, 0);
    $pdf->Cell(80, 5, ': RS Bhayangkara Tk. III Banjarmasin', 0, 0);
    $pdf->Cell(45, 5, 'Tahun Anggaran / Periode', 0, 0);
    $pdf->Cell(0, 5, ': ' . date('Y'), 0, 1);

    $pdf->Cell(45, 5, 'Ruangan / Instalasi', 0, 0);
    $pdf->Cell(80, 5, ': ' . $filter_ruangan, 0, 0);
    $pdf->Cell(45, 5, 'Tanggal Pemeriksaan Fisik', 0, 0);
    $pdf->Cell(0, 5, ': ' . date('d/m/Y'), 0, 1);
    $pdf->Ln(3);

    // Ambil Data Aset Ruangan
    $safeRuangan = mysqli_real_escape_string($koneksi, $filter_ruangan);
    $qAset = mysqli_query($koneksi, "SELECT * FROM aset WHERE lokasi = '$safeRuangan' ORDER BY kategori_aset DESC, nama_aset ASC");

    $pdf->renderTableHeader();

    $no = 1;
    $sumBaik = 0;
    $sumRusak = 0;
    $sumPerawatan = 0;
    $sumTotal = 0;

    $pdf->SetFont('Arial', '', 8.5);
    $pdf->SetTextColor(30, 41, 59);

    if (mysqli_num_rows($qAset) == 0) {
        $pdf->Cell(270, 12, 'Belum ada aset terdata yang ditempatkan pada ruangan ini.', 1, 1, 'C');
    } else {
        while ($a = mysqli_fetch_assoc($qAset)) {
            // Page break manual protection agar table header tercetak ulang
            if ($pdf->GetY() > 170) {
                $pdf->AddPage();
                $pdf->renderTableHeader();
                $pdf->SetFont('Arial', '', 8.5);
            }

            if (empty($a['kode_aset'])) {
                $prefix = ($a['kategori_aset'] == 'Medis') ? 'RSB-MED' : 'RSB-NON';
                $a['kode_aset'] = sprintf("%s-%03d", $prefix, $a['id_aset']);
            }

            $baik = (int)$a['stok_tersedia'];
            $rusak = (int)$a['stok_rusak'];
            $perawatan = (int)$a['stok_perawatan'];
            $total = (int)$a['total_stok'];

            $sumBaik += $baik;
            $sumRusak += $rusak;
            $sumPerawatan += $perawatan;
            $sumTotal += $total;

            $pdf->Cell(8, 7, $no++, 1, 0, 'C');
            $pdf->Cell(25, 7, $a['kode_aset'], 1, 0, 'C');
            
            // Nama Aset dipotong jika kepanjangan
            $nama = strlen($a['nama_aset']) > 35 ? substr($a['nama_aset'], 0, 33) . '..' : $a['nama_aset'];
            $pdf->Cell(62, 7, $nama, 1, 0, 'L');

            // Merk / Tipe
            $spek = $a['jenis'] . (!empty($a['tipe_aset']) ? ' - ' . $a['tipe_aset'] : '');
            $spek = strlen($spek) > 26 ? substr($spek, 0, 24) . '..' : $spek;
            $pdf->Cell(45, 7, $spek, 1, 0, 'L');

            $pdf->Cell(22, 7, $a['kategori_aset'], 1, 0, 'C');
            $pdf->Cell(16, 7, $baik, 1, 0, 'C');
            $pdf->Cell(16, 7, $rusak, 1, 0, 'C');
            $pdf->Cell(16, 7, $perawatan, 1, 0, 'C');
            $pdf->Cell(15, 7, $total, 1, 0, 'C');
            $pdf->Cell(45, 7, 'Terinventarisir Lengkap', 1, 1, 'L');
        }

        // Baris Total Akumulasi
        $pdf->SetFont('Arial', 'B', 8.5);
        $pdf->SetFillColor(241, 245, 249);
        $pdf->Cell(162, 7, 'TOTAL KESELURUHAN UNIT FISIK DI RUANGAN INI', 1, 0, 'R', true);
        $pdf->Cell(16, 7, $sumBaik, 1, 0, 'C', true);
        $pdf->Cell(16, 7, $sumRusak, 1, 0, 'C', true);
        $pdf->Cell(16, 7, $sumPerawatan, 1, 0, 'C', true);
        $pdf->Cell(15, 7, $sumTotal, 1, 0, 'C', true);
        $pdf->Cell(45, 7, '', 1, 1, 'C', true);
    }

    // Tanda Tangan 2 Kolom Resmi
    renderReportSignature($pdf, $koneksi, [
        'pj_jabatan' => 'Penanggung Jawab ' . $filter_ruangan,
        'pj_nama'    => 'Pengelola Ruangan'
    ]);

} else {
    // CETAK REKAPITULASI SEBARAN SELURUH RUANGAN
    $pdf->AddPage();
    $pdf->renderKopSurat();

    $pdf->SetFont('Arial', 'B', 13);
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(0, 7, 'REKAPITULASI SEBARAN ASET & KARTU INVENTARIS RUANGAN (KIR)', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 5, 'Distribusi Fasilitas, Alkes, dan Non-Medis per Unit Pelayanan Rumah Sakit', 0, 1, 'C');
    $pdf->Ln(4);

    // Table Header Rekap Ruangan
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(240, 243, 246);
    $pdf->SetTextColor(30, 41, 59);

    $pdf->Cell(10, 9, 'NO', 1, 0, 'C', true);
    $pdf->Cell(85, 9, 'NAMA RUANGAN / UNIT PELAYANAN', 1, 0, 'C', true);
    $pdf->Cell(28, 9, 'JML ITEM ASET', 1, 0, 'C', true);
    $pdf->Cell(25, 9, 'ASET MEDIS', 1, 0, 'C', true);
    $pdf->Cell(28, 9, 'NON-MEDIS', 1, 0, 'C', true);
    $pdf->Cell(22, 9, 'JML BAIK', 1, 0, 'C', true);
    $pdf->Cell(22, 9, 'JML RUSAK', 1, 0, 'C', true);
    $pdf->Cell(22, 9, 'PERAWATAN', 1, 0, 'C', true);
    $pdf->Cell(28, 9, 'TOTAL UNIT', 1, 1, 'C', true);

    $qAll = mysqli_query($koneksi, "SELECT 
        lokasi,
        COUNT(*) as total_aset,
        SUM(total_stok) as sum_stok,
        SUM(stok_tersedia) as sum_tersedia,
        SUM(stok_rusak) as sum_rusak,
        SUM(stok_perawatan) as sum_perawatan,
        SUM(CASE WHEN kategori_aset = 'Medis' THEN 1 ELSE 0 END) as jml_medis,
        SUM(CASE WHEN kategori_aset = 'Non-Medis' THEN 1 ELSE 0 END) as jml_nonmedis
    FROM aset 
    WHERE lokasi IS NOT NULL AND lokasi != ''
    GROUP BY lokasi 
    ORDER BY total_aset DESC, lokasi ASC");

    $no = 1;
    $totItem = 0; $totMedis = 0; $totNon = 0; $totB = 0; $totR = 0; $totP = 0; $totU = 0;

    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(30, 41, 59);

    while ($r = mysqli_fetch_assoc($qAll)) {
        if ($pdf->GetY() > 175) {
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(240, 243, 246);
            $pdf->Cell(10, 9, 'NO', 1, 0, 'C', true);
            $pdf->Cell(85, 9, 'NAMA RUANGAN / UNIT PELAYANAN', 1, 0, 'C', true);
            $pdf->Cell(28, 9, 'JML ITEM ASET', 1, 0, 'C', true);
            $pdf->Cell(25, 9, 'ASET MEDIS', 1, 0, 'C', true);
            $pdf->Cell(28, 9, 'NON-MEDIS', 1, 0, 'C', true);
            $pdf->Cell(22, 9, 'JML BAIK', 1, 0, 'C', true);
            $pdf->Cell(22, 9, 'JML RUSAK', 1, 0, 'C', true);
            $pdf->Cell(22, 9, 'PERAWATAN', 1, 0, 'C', true);
            $pdf->Cell(28, 9, 'TOTAL UNIT', 1, 1, 'C', true);
            $pdf->SetFont('Arial', '', 9);
        }

        $totItem += (int)$r['total_aset'];
        $totMedis += (int)$r['jml_medis'];
        $totNon += (int)$r['jml_nonmedis'];
        $totB += (int)$r['sum_tersedia'];
        $totR += (int)$r['sum_rusak'];
        $totP += (int)$r['sum_perawatan'];
        $totU += (int)$r['sum_stok'];

        $pdf->Cell(10, 7, $no++, 1, 0, 'C');
        $pdf->Cell(85, 7, $r['lokasi'], 1, 0, 'L');
        $pdf->Cell(28, 7, $r['total_aset'] . ' Jenis', 1, 0, 'C');
        $pdf->Cell(25, 7, $r['jml_medis'], 1, 0, 'C');
        $pdf->Cell(28, 7, $r['jml_nonmedis'], 1, 0, 'C');
        $pdf->Cell(22, 7, $r['sum_tersedia'], 1, 0, 'C');
        $pdf->Cell(22, 7, $r['sum_rusak'], 1, 0, 'C');
        $pdf->Cell(22, 7, $r['sum_perawatan'], 1, 0, 'C');
        $pdf->Cell(28, 7, $r['sum_stok'] . ' Unit', 1, 1, 'C');
    }

    // Baris Total Rekap
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(241, 245, 249);
    $pdf->Cell(95, 8, 'TOTAL DISTRIBUSI SELURUH RUMAH SAKIT', 1, 0, 'R', true);
    $pdf->Cell(28, 8, $totItem . ' Jenis', 1, 0, 'C', true);
    $pdf->Cell(25, 8, $totMedis, 1, 0, 'C', true);
    $pdf->Cell(28, 8, $totNon, 1, 0, 'C', true);
    $pdf->Cell(22, 8, $totB, 1, 0, 'C', true);
    $pdf->Cell(22, 8, $totR, 1, 0, 'C', true);
    $pdf->Cell(22, 8, $totP, 1, 0, 'C', true);
    $pdf->Cell(28, 8, $totU . ' Unit', 1, 1, 'C', true);

    // Tanda Tangan 2 Kolom Resmi
    renderReportSignature($pdf, $koneksi, [
        'pj_jabatan' => 'Koordinator Sarana & Prasarana',
        'pj_nama'    => $_SESSION['nama_pengguna'] ?? 'Petugas Sarpras'
    ]);
}

$pdf->Output('I', 'KIR_RS_Bhayangkara_' . date('Ymd_His') . '.pdf');
