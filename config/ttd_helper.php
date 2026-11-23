<?php
/**
 * Helper untuk Tanda Tangan Laporan SIMARIS RS Bhayangkara Tk. III Banjarmasin
 * Format 2 Kolom:
 * - Kiri: Penanggung Jawab Laporan (Petugas login / Staf Sarpras)
 * - Kanan: Mengetahui Kepala Rumah Sakit Bhayangkara Tk. III Banjarmasin
 */

if (!function_exists('renderOfficialKopSurat')) {
    function renderOfficialKopSurat($pdf, $orientation = 'L')
    {
        $logo1 = realpath(__DIR__ . '/../assets/img/logo_dokpol.png');
        $logo2 = realpath(__DIR__ . '/../assets/img/logo_rs.jpg');

        $xRightLogo = ($orientation === 'L') ? 260 : 175;
        $lineWidth  = ($orientation === 'L') ? 282 : 197;

        if ($logo1 && file_exists($logo1)) {
            $pdf->Image($logo1, 15, 8, 22);
        }
        if ($logo2 && file_exists($logo2)) {
            $pdf->Image($logo2, $xRightLogo, 8, 22);
        }

        $pdf->SetY(8);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 5, 'KEPOLISIAN NEGARA REPUBLIK INDONESIA', 0, 1, 'C');
        $pdf->Cell(0, 5, 'DAERAH KALIMANTAN SELATAN', 0, 1, 'C');
        $pdf->Cell(0, 5, 'BIDANG KEDOKTERAN DAN KESEHATAN', 0, 1, 'C');
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->Cell(0, 6, 'RUMAH SAKIT BHAYANGKARA TK. III BANJARMASIN', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 8.5);
        $pdf->Cell(0, 4, 'Jl. A. Yani Km. 3,5 No. 477 Banjarmasin | Telp: (0511) 3256088', 0, 1, 'C');

        // Garis Ganda Kop Surat Resmi Kedinasan
        $pdf->SetLineWidth(0.8);
        $pdf->Line(15, 36, $lineWidth, 36);
        $pdf->SetLineWidth(0.2);
        $pdf->Line(15, 37, $lineWidth, 37);
        $pdf->Ln(4);
    }
}

if (!function_exists('renderReportSignature')) {
    function renderReportSignature($pdf, $koneksi, $options = [])
    {
        // Tanggal Format Indonesia (Banjarmasin, d Bulan Y)
        $bulanIndo = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        $tglCetak = date('d') . ' ' . $bulanIndo[(int)date('m')] . ' ' . date('Y');

        // 1. Data Penanggung Jawab Laporan (Kolom Kiri)
        $pjNama = $_SESSION['nama_pengguna'] ?? 'Petugas Sarana & Prasarana';
        $pjNip  = $_SESSION['nip'] ?? '';
        $pjJabatan = $_SESSION['jabatan'] ?? 'Pengelola Sarpras & Aset';

        // Jika belum ada di session, coba tarik dari database berdasarkan id_pengguna
        if (!empty($_SESSION['id_pengguna'])) {
            $idUser = intval($_SESSION['id_pengguna']);
            $qU = @mysqli_query($koneksi, "SELECT nama_pengguna, nip, jabatan FROM pengguna WHERE id_pengguna = $idUser LIMIT 1");
            if ($qU && $u = mysqli_fetch_assoc($qU)) {
                if (!empty($u['nama_pengguna'])) $pjNama = $u['nama_pengguna'];
                if (!empty($u['nip'])) $pjNip = $u['nip'];
                if (!empty($u['jabatan'])) $pjJabatan = $u['jabatan'];
            }
        }

        // Override opsional dari parameter $options
        if (!empty($options['pj_nama'])) $pjNama = $options['pj_nama'];
        if (!empty($options['pj_nip'])) $pjNip = $options['pj_nip'];
        if (!empty($options['pj_jabatan'])) $pjJabatan = $options['pj_jabatan'];

        // 2. Data Pimpinan / Kepala Rumah Sakit (Kolom Kanan)
        $pimpinanNama = 'dr. Bambang Pitoyo, Sp.S., M.H.';
        $pimpinanJabatan = 'Kepala Rumkit Bhayangkara Tk. III';
        $pimpinanNip = '197005151998031002';
        $pimpinanTtd = 'ttd_kepala.png';

        $qSig = @mysqli_query($koneksi, "SELECT * FROM pejabat_ttd WHERE jabatan LIKE '%Kepala%' LIMIT 1");
        if ($qSig && mysqli_num_rows($qSig) > 0) {
            $rSig = mysqli_fetch_assoc($qSig);
            if (!empty($rSig['nama_pejabat'])) $pimpinanNama = $rSig['nama_pejabat'];
            if (!empty($rSig['jabatan'])) $pimpinanJabatan = $rSig['jabatan'];
            if (!empty($rSig['nip'])) $pimpinanNip = $rSig['nip'];
            if (!empty($rSig['tanda_tangan'])) $pimpinanTtd = $rSig['tanda_tangan'];
        }

        // Override opsional dari parameter $options
        if (!empty($options['pimpinan_nama'])) $pimpinanNama = $options['pimpinan_nama'];
        if (!empty($options['pimpinan_nip'])) $pimpinanNip = $options['pimpinan_nip'];
        if (!empty($options['pimpinan_jabatan'])) $pimpinanJabatan = $options['pimpinan_jabatan'];

        // 3. Page Break Protection (Tinggi blok tanda tangan ~45mm)
        // Batas tinggi Landscape A4 adalah 210mm. Margin bawah 15mm => jika > 145mm, pindah halaman
        if ($pdf->GetY() > 145) {
            $pdf->AddPage();
        }

        $pdf->Ln(6);
        $yStart = $pdf->GetY();

        $xLeft = 15;
        $xRight = 175;
        $colWidth = 105;

        // Tanggal Cetak di Kolom Kanan
        $pdf->SetXY($xRight, $yStart);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell($colWidth, 5, 'Banjarmasin, ' . $tglCetak, 0, 1, 'C');

        $yJabatan = $pdf->GetY();

        // Kolom Kiri: Penanggung Jawab Laporan
        $pdf->SetXY($xLeft, $yJabatan);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell($colWidth, 5, 'Penanggung Jawab Laporan,', 0, 1, 'C');
        $pdf->SetX($xLeft);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($colWidth, 5, $pjJabatan, 0, 1, 'C');

        // Kolom Kanan: Mengetahui Kepala RS
        $pdf->SetXY($xRight, $yJabatan);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell($colWidth, 5, 'Mengetahui,', 0, 1, 'C');
        $pdf->SetX($xRight);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($colWidth, 5, $pimpinanJabatan, 0, 1, 'C');

        // Ruang Tanda Tangan Fisik (~20mm)
        $yAfterHeader = max($pdf->GetY(), $yJabatan + 10);
        $yName = $yAfterHeader + 20;

        // Cek jika ada file gambar tanda tangan pimpinan
        if (!empty($pimpinanTtd)) {
            $ttdPath = realpath(__DIR__ . '/../assets/img/' . $pimpinanTtd);
            if ($ttdPath && file_exists($ttdPath)) {
                $pdf->Image($ttdPath, $xRight + 35, $yAfterHeader + 1, 35);
            }
        }

        // Kolom Kiri: Nama & NIP
        $pdf->SetXY($xLeft, $yName);
        $pdf->SetFont('Arial', 'BU', 10);
        $pdf->Cell($colWidth, 5, $pjNama, 0, 1, 'C');
        $pdf->SetX($xLeft);
        $pdf->SetFont('Arial', '', 9);
        $nipText = !empty($pjNip) ? 'NIP. ' . $pjNip : 'NIP. ........................................';
        $pdf->Cell($colWidth, 4, $nipText, 0, 1, 'C');

        // Kolom Kanan: Nama & NIP/NRP
        $pdf->SetXY($xRight, $yName);
        $pdf->SetFont('Arial', 'BU', 10);
        $pdf->Cell($colWidth, 5, $pimpinanNama, 0, 1, 'C');
        $pdf->SetX($xRight);
        $pdf->SetFont('Arial', '', 9);
        $pimpinanNipText = !empty($pimpinanNip) ? 'NIP/NRP. ' . $pimpinanNip : 'NIP/NRP. ........................................';
        $pdf->Cell($colWidth, 4, $pimpinanNipText, 0, 1, 'C');

        // Catatan Kaki Cetak
        $pdf->SetY($pdf->GetY() + 6);
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->SetTextColor(128, 128, 128);
        $pdf->Cell(0, 4, 'SIMARIS RS Bhayangkara Tk. III Banjarmasin - Dicetak pada: ' . date('d-m-Y H:i:s') . ' WITA oleh ' . $pjNama, 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);
    }
}
