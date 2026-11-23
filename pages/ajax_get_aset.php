<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

include(__DIR__ . '/../config/koneksi.php');

$action = $_REQUEST['action'] ?? 'lookup';

if ($action === 'lookup') {
    $rawCode = trim($_REQUEST['kode'] ?? $_REQUEST['code'] ?? $_REQUEST['id'] ?? '');

    if (empty($rawCode)) {
        echo json_encode(['success' => false, 'message' => 'Kode atau ID aset tidak boleh kosong.']);
        exit;
    }

    // Ekstrak kode jika input berupa URL lengkap (misal hasil scan kamera)
    if (preg_match('/kode=([A-Za-z0-9\-]+)/', $rawCode, $matches)) {
        $rawCode = $matches[1];
    } elseif (preg_match('/id=([0-9]+)/', $rawCode, $matches)) {
        $rawCode = $matches[1];
    }

    $safeCode = mysqli_real_escape_string($koneksi, $rawCode);

    // Cari berdasarkan kode_aset ATAU id_aset
    $query = mysqli_query($koneksi, "SELECT * FROM aset WHERE kode_aset = '$safeCode' OR id_aset = '$safeCode' LIMIT 1");
    if (!$query || mysqli_num_rows($query) === 0) {
        // Coba pencarian toleran LIKE
        $query = mysqli_query($koneksi, "SELECT * FROM aset WHERE kode_aset LIKE '%$safeCode%' OR nama_aset LIKE '%$safeCode%' LIMIT 1");
    }

    if ($query && $data = mysqli_fetch_assoc($query)) {
        $kodeAset = !empty($data['kode_aset']) ? $data['kode_aset'] : sprintf("RSB-%03d", $data['id_aset']);
        
        $baseDir = rtrim(dirname(dirname($_SERVER['REQUEST_URI'] ?? '/simaris_rs/pages')), '/\\');
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
        $detailUrl = $protocol . "://" . $host . $baseDir . "/pages/detail_aset_qr.php?kode=" . urlencode($kodeAset);
        $qrImgUrl = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=" . urlencode($detailUrl);

        // Ambil riwayat perpindahan lokasi terakhir
        $qRiwayat = mysqli_query($koneksi, "SELECT * FROM riwayat_lokasi WHERE id_aset = {$data['id_aset']} ORDER BY id_riwayat DESC LIMIT 3");
        $riwayat = [];
        while ($r = mysqli_fetch_assoc($qRiwayat)) {
            $riwayat[] = [
                'lokasi_sebelumnya' => $r['lokasi_sebelumnya'],
                'lokasi_baru' => $r['lokasi_baru'],
                'tanggal_pindah' => date('d-m-Y', strtotime($r['tanggal_pindah'])),
                'keterangan' => $r['keterangan'] ?? '-',
                'penanggung_jawab' => $r['penanggung_jawab'] ?? '-'
            ];
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'id_aset' => (int)$data['id_aset'],
                'kode_aset' => $kodeAset,
                'nama_aset' => $data['nama_aset'],
                'kategori_aset' => $data['kategori_aset'],
                'jenis' => $data['jenis'],
                'tipe_aset' => $data['tipe_aset'],
                'lokasi' => $data['lokasi'],
                'total_stok' => (int)($data['total_stok'] ?? 0),
                'stok_tersedia' => (int)($data['stok_tersedia'] ?? 0),
                'stok_rusak' => (int)($data['stok_rusak'] ?? 0),
                'stok_perawatan' => (int)($data['stok_perawatan'] ?? 0),
                'asal_usul' => $data['asal_usul'],
                'harga' => (float)$data['harga'],
                'harga_format' => 'Rp ' . number_format($data['harga'], 0, ',', '.'),
                'umur_ekonomis' => (int)$data['umur_ekonomis'],
                'tanggal_masuk' => $data['tanggal_masuk'],
                'tanggal_masuk_format' => date('d-m-Y', strtotime($data['tanggal_masuk'])),
                'detail_url' => $detailUrl,
                'qr_img_url' => $qrImgUrl,
                'riwayat' => $riwayat
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => "Aset dengan kode/ID '$safeCode' tidak ditemukan dalam sistem."]);
    }
    exit;
}

if ($action === 'pindah_lokasi') {
    if (!isset($_SESSION['id_pengguna'])) {
        echo json_encode(['success' => false, 'message' => 'Sesi berakhir, silakan login kembali.']);
        exit;
    }

    $id_aset = (int)($_POST['id_aset'] ?? 0);
    $lokasi_baru = trim($_POST['lokasi_baru'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? 'Mutasi/Pindah Lokasi via Scan QR');
    $penanggung_jawab = trim($_POST['penanggung_jawab'] ?? $_SESSION['nama_pengguna'] ?? 'Petugas Sarpras');

    if ($id_aset <= 0 || empty($lokasi_baru)) {
        echo json_encode(['success' => false, 'message' => 'Data perpindahan lokasi tidak lengkap.']);
        exit;
    }

    // Ambil data lokasi sekarang
    $qAset = mysqli_query($koneksi, "SELECT lokasi, nama_aset, kode_aset FROM aset WHERE id_aset = $id_aset LIMIT 1");
    if (!$qAset || mysqli_num_rows($qAset) === 0) {
        echo json_encode(['success' => false, 'message' => 'Aset tidak ditemukan.']);
        exit;
    }

    $aset = mysqli_fetch_assoc($qAset);
    $lokasi_lama = $aset['lokasi'];

    if ($lokasi_lama === $lokasi_baru) {
        echo json_encode(['success' => false, 'message' => "Aset sudah berada di lokasi '$lokasi_baru'."]);
        exit;
    }

    $safeLokasiBaru = mysqli_real_escape_string($koneksi, $lokasi_baru);
    $safeLokasiLama = mysqli_real_escape_string($koneksi, $lokasi_lama);
    $safeKet = mysqli_real_escape_string($koneksi, $keterangan);
    $safePj = mysqli_real_escape_string($koneksi, $penanggung_jawab);
    $tglPindah = date('Y-m-d H:i:s');

    // Catat riwayat
    $insertRiwayat = mysqli_query($koneksi, "INSERT INTO riwayat_lokasi (id_aset, lokasi_sebelumnya, lokasi_baru, tanggal_pindah, keterangan, penanggung_jawab) 
        VALUES ($id_aset, '$safeLokasiLama', '$safeLokasiBaru', '$tglPindah', '$safeKet', '$safePj')");

    // Perbarui lokasi aset
    $updateAset = mysqli_query($koneksi, "UPDATE aset SET lokasi = '$safeLokasiBaru' WHERE id_aset = $id_aset");

    if ($updateAset) {
        echo json_encode([
            'success' => true,
            'message' => "Berhasil memindahkan aset '{$aset['nama_aset']}' dari '$lokasi_lama' ke '$lokasi_baru'.",
            'lokasi_lama' => $lokasi_lama,
            'lokasi_baru' => $lokasi_baru
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui lokasi aset: ' . mysqli_error($koneksi)]);
    }
    exit;
}

if ($action === 'list_ruangan') {
    $listRuangan = [];
    $q = mysqli_query($koneksi, "SELECT DISTINCT lokasi FROM aset WHERE lokasi IS NOT NULL AND lokasi != '' ORDER BY lokasi ASC");
    while ($r = mysqli_fetch_assoc($q)) {
        $listRuangan[] = $r['lokasi'];
    }
    echo json_encode(['success' => true, 'ruangan' => $listRuangan]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Aksi tidak valid.']);
