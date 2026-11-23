<?php
session_start();
if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../login.php");
    exit;
}

include(__DIR__ . '/../config/koneksi.php');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$kode = isset($_GET['kode']) ? mysqli_real_escape_string($koneksi, $_GET['kode']) : '';
$kategori = isset($_GET['kategori']) ? mysqli_real_escape_string($koneksi, $_GET['kategori']) : '';
$lokasi = isset($_GET['lokasi']) ? mysqli_real_escape_string($koneksi, $_GET['lokasi']) : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($koneksi, $_GET['search']) : '';

$where = [];
if ($id > 0) {
    $where[] = "id_aset = $id";
} elseif (!empty($kode)) {
    $where[] = "kode_aset = '$kode'";
} else {
    if (!empty($kategori) && strtolower($kategori) !== 'semua') {
        $where[] = "kategori_aset = '$kategori'";
    }
    if (!empty($lokasi)) {
        $where[] = "lokasi = '$lokasi'";
    }
    if (!empty($search)) {
        $where[] = "(nama_aset LIKE '%$search%' OR kode_aset LIKE '%$search%' OR jenis LIKE '%$search%')";
    }
}

$whereClause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
$query = mysqli_query($koneksi, "SELECT * FROM aset $whereClause ORDER BY id_aset ASC");

$assets = [];
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
$baseDir = rtrim(dirname(dirname($_SERVER['REQUEST_URI'] ?? '/simaris_rs/pages')), '/\\');

while ($row = mysqli_fetch_assoc($query)) {
    if (empty($row['kode_aset'])) {
        $prefix = ($row['kategori_aset'] == 'Medis') ? 'RSB-MED' : 'RSB-NON';
        $row['kode_aset'] = sprintf("%s-%03d", $prefix, $row['id_aset']);
    }
    $targetUrl = $protocol . "://" . $host . $baseDir . "/pages/detail_aset_qr.php?kode=" . urlencode($row['kode_aset']);
    $row['qr_url'] = "https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=" . urlencode($targetUrl);
    $assets[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Label QR Code Aset | SIMARIS RS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&family=Roboto+Mono:wght@600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #f1f5f9;
            font-family: 'Poppins', sans-serif;
            color: #1e293b;
            margin: 0;
            padding: 20px 0;
        }

        .control-bar {
            background: #ffffff;
            border-radius: 12px;
            padding: 14px 24px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.06);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        /* Container Label Grid */
        .label-sheet {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(330px, 1fr));
            gap: 16px;
            padding: 5px;
        }

        /* Label Sticker Card */
        .sticker-card {
            background: #ffffff;
            border: 2px solid #0f172a;
            border-radius: 10px;
            padding: 10px 12px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            page-break-inside: avoid;
            break-inside: avoid;
            position: relative;
            box-sizing: border-box;
            min-height: 180px;
        }

        .sticker-header {
            border-bottom: 1.5px solid #0f172a;
            padding-bottom: 6px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sticker-logo {
            width: 32px;
            height: 32px;
            object-fit: contain;
        }

        .sticker-hospital-title {
            font-size: 8.5pt;
            font-weight: 700;
            line-height: 1.15;
            color: #0f172a;
            text-transform: uppercase;
        }

        .sticker-hospital-sub {
            font-size: 6.5pt;
            color: #475569;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .sticker-body {
            display: flex;
            gap: 10px;
            align-items: center;
            flex: 1;
        }

        .sticker-qr-box {
            width: 96px;
            height: 96px;
            min-width: 96px;
            border: 1px solid #cbd5e1;
            padding: 3px;
            background: #fff;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sticker-qr-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .sticker-info {
            flex: 1;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .sticker-code {
            font-family: 'Roboto Mono', monospace;
            font-size: 11pt;
            font-weight: 700;
            color: #0f172a;
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 4px;
            display: inline-block;
            border: 1px dashed #94a3b8;
            margin-bottom: 2px;
        }

        .sticker-name {
            font-size: 9pt;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
            margin-bottom: 2px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .sticker-meta {
            font-size: 7.5pt;
            color: #334155;
            line-height: 1.3;
        }

        .sticker-meta span {
            font-weight: 600;
        }

        .sticker-footer {
            margin-top: 6px;
            padding-top: 4px;
            border-top: 1px dashed #94a3b8;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 6.5pt;
            color: #64748b;
            font-weight: 600;
            letter-spacing: 0.3px;
        }

        .badge-kat {
            font-size: 6.5pt;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 700;
            text-transform: uppercase;
        }

        /* PRINT STYLES */
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .control-bar {
                display: none !important;
            }

            .container {
                max-width: 100% !important;
                width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .label-sheet {
                grid-template-columns: repeat(3, 1fr) !important;
                gap: 8px !important;
                padding: 0 !important;
            }

            .sticker-card {
                box-shadow: none !important;
                border: 1.5px solid #000 !important;
                min-height: 170px !important;
            }

            .sticker-header {
                border-bottom: 1.5px solid #000 !important;
            }

            .sticker-code {
                background: #eee !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Control Bar -->
        <div class="control-bar">
            <div>
                <h5 class="fw-bold mb-1"><i class="bi bi-qr-code"></i> Cetak Stiker Label QR Code Aset</h5>
                <p class="text-muted small mb-0">Total <strong><?= count($assets) ?></strong> label aset siap dicetak pada kertas stiker / HVS.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="aset.php" class="btn btn-outline-secondary btn-sm px-3">
                    <i class="bi bi-arrow-left"></i> Kembali
                </a>
                <button onclick="window.print()" class="btn btn-primary btn-sm px-4 fw-bold">
                    <i class="bi bi-printer-fill me-1"></i> Cetak Label Sekarang
                </button>
            </div>
        </div>

        <?php if (empty($assets)): ?>
            <div class="alert alert-warning text-center p-4">
                <i class="bi bi-exclamation-circle fs-3 d-block mb-2"></i>
                Tidak ada data aset yang cocok untuk dicetak labelnya.
            </div>
        <?php else: ?>
            <!-- Sheet Container -->
            <div class="label-sheet">
                <?php foreach ($assets as $a): ?>
                    <div class="sticker-card">
                        <!-- Header Stiker -->
                        <div class="sticker-header">
                            <img src="../assets/img/logo_rs.jpg" alt="Logo RS" class="sticker-logo" onerror="this.src='../assets/img/logo.png'">
                            <div>
                                <div class="sticker-hospital-title">RS BHAYANGKARA TK. III BANJARMASIN</div>
                                <div class="sticker-hospital-sub">SISTEM INVENTARISASI & PELACAKAN ASET</div>
                            </div>
                        </div>

                        <!-- Body Stiker -->
                        <div class="sticker-body">
                            <!-- QR Code Box -->
                            <div class="sticker-qr-box">
                                <img src="<?= $a['qr_url'] ?>" alt="QR Code <?= $a['kode_aset'] ?>" loading="lazy">
                            </div>

                            <!-- Detail Aset -->
                            <div class="sticker-info">
                                <div>
                                    <span class="sticker-code"><?= htmlspecialchars($a['kode_aset']) ?></span>
                                    <span class="badge-kat <?= $a['kategori_aset'] == 'Medis' ? 'bg-danger text-white' : 'bg-primary text-white' ?>">
                                        <?= htmlspecialchars($a['kategori_aset']) ?>
                                    </span>
                                </div>
                                <div class="sticker-name" title="<?= htmlspecialchars($a['nama_aset']) ?>">
                                    <?= htmlspecialchars($a['nama_aset']) ?>
                                </div>
                                <div class="sticker-meta">
                                    <div><i class="bi bi-geo-alt-fill text-danger"></i> <span><?= htmlspecialchars($a['lokasi']) ?></span></div>
                                    <div><i class="bi bi-tag-fill text-secondary"></i> <?= htmlspecialchars($a['jenis']) ?> (<?= htmlspecialchars($a['tipe_aset']) ?>)</div>
                                    <div><i class="bi bi-calendar-check text-success"></i> Thn Masuk: <?= date('Y', strtotime($a['tanggal_masuk'])) ?> | Jml: <?= $a['total_stok'] ?> Unit</div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer Stiker -->
                        <div class="sticker-footer">
                            <span>SCAN UNTUK CEK / MUTASI</span>
                            <span>MILIK RUMKIT BHAYANGKARA</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>
