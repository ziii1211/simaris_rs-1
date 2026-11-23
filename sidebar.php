<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Ambil dari 'level' atau 'role' sesuai session sistem
$role = $_SESSION['level'] ?? $_SESSION['role'] ?? '';

// Hitung jumlah pengajuan yang berstatus 'Menunggu Persetujuan'
if (!isset($koneksi)) {
    include(__DIR__ . '/config/koneksi.php');
}
$q_notif_sidebar = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM peminjaman WHERE status_pinjam = 'Menunggu Persetujuan'");
$data_notif_sidebar = mysqli_fetch_assoc($q_notif_sidebar);
$jumlah_pending = $data_notif_sidebar['total'] ?? 0;

$cur_page = basename($_SERVER['PHP_SELF'] ?? '');
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

    #sidebar-wrapper {
        width: 315px;
        min-width: 315px;
        background: linear-gradient(180deg, #2c7a7b, #1cc88a);
        /* Gradasi warna asli tetap dipertahankan */
        color: #fff;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        font-family: 'Inter', sans-serif;
        box-shadow: 4px 0 14px rgba(0, 0, 0, 0.08);
    }

    .sidebar-heading {
        padding: 2.2rem 1.5rem 1.2rem 1.5rem;
        font-size: 1.35rem;
        font-weight: 800;
        text-align: center;
        letter-spacing: 0.6px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
    }

    /* Style untuk Logo Rumah Sakit Tempat Magang */
    .sidebar-logo {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 50%;
        border: 3px solid rgba(255, 255, 255, 0.7);
        background-color: #fff;
        padding: 2px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
    }

    /* Pengelompokan Kategori Menu - BESAR & JELAS */
    .menu-category {
        font-size: 0.85rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        color: rgba(255, 255, 255, 0.9);
        padding: 1.3rem 16px 0.5rem 16px;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
    }

    .list-group {
        padding: 0 12px;
        flex: 1;
        /* Membuat pendorong agar tombol logout bisa otomatis ke bawah */
        display: flex;
        flex-direction: column;
    }

    .list-group-item {
        background: transparent !important;
        color: #ffffff !important;
        border: none !important;
        margin: 3px 0;
        padding: 12px 18px;
        border-radius: 10px !important;
        display: flex;
        align-items: center;
        justify-content: space-between;
        text-decoration: none;
        font-size: 17px;
        font-weight: 600;
        letter-spacing: 0.2px;
        transition: all 0.2s ease-in-out;
    }

    /* Efek hover modern bergeser sedikit ke kanan */
    .list-group-item:hover {
        background: rgba(255, 255, 255, 0.2) !important;
        color: #fff !important;
        padding-left: 24px;
    }

    /* Efek Menu yang Sedang Terbuka / Aktif - JELAS & TEGAS */
    .list-group-item.active {
        background: rgba(255, 255, 255, 0.28) !important;
        color: #fff !important;
        font-weight: 800 !important;
        padding-left: 22px;
        border-left: 6px solid #ffffff !important;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.15);
    }

    .list-group-item div {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    /* Ikon Menu Jauh Lebih Besar */
    .list-group-item i {
        font-size: 1.35rem;
    }

    /* Badge Bulat Merah Cantik untuk Notifikasi */
    .badge-notif {
        background-color: #ef4444 !important;
        color: white !important;
        font-size: 0.85rem;
        font-weight: 800;
        padding: 4px 9px !important;
        border-radius: 50rem !important;
        box-shadow: 0 2px 6px rgba(239, 68, 68, 0.45);
    }

    /* Style Khusus Tombol Logout Merah Modern di Paling Bawah */
    .logout-item {
        margin-top: auto !important;
        /* Mendorong tombol otomatis ke dasar sidebar */
        margin-bottom: 20px;
        background: rgba(239, 68, 68, 0.2) !important;
        color: #fecaca !important;
        border: 1px solid rgba(239, 68, 68, 0.4) !important;
        font-weight: 700;
        font-size: 17px;
        padding: 13px 18px;
    }

    .logout-item:hover {
        background: #ef4444 !important;
        color: #fff !important;
        padding-left: 24px;
        box-shadow: 0 4px 14px rgba(239, 68, 68, 0.35);
    }
</style>

<div id="sidebar-wrapper">
    <div class="sidebar-heading">
        <img src="../assets/img/logo.png" alt="Logo Rumah Sakit" class="sidebar-logo">
        <span>SIMARIS RS</span>
    </div>

    <div class="list-group list-group-flush">
        <div class="menu-category">Utama</div>
        <a href="dashboard.php" class="list-group-item <?= $cur_page === 'dashboard.php' ? 'active' : '' ?>">
            <div><i class="bi bi-speedometer2"></i> Dashboard</div>
        </a>

        <div class="menu-category">Manajemen Aset (Sarana & Alkes)</div>
        <a href="aset.php" class="list-group-item <?= in_array($cur_page, ['aset.php', 'aset_tambah.php', 'aset_edit.php']) ? 'active' : '' ?>">
            <div><i class="bi bi-box-seam"></i> Data Aset (Barang/Alkes)</div>
        </a>
        <a href="scan_aset.php" class="list-group-item <?= $cur_page === 'scan_aset.php' ? 'active' : '' ?>">
            <div><i class="bi bi-qr-code-scan"></i> Scan QR / Barcode</div>
        </a>
        <a href="peminjaman.php" class="list-group-item <?= in_array($cur_page, ['peminjaman.php', 'peminjaman_tambah.php', 'peminjaman_edit.php']) ? 'active' : '' ?>">
            <div><i class="bi bi-arrow-left-right"></i> Peminjaman Alat (Alkes)</div>
            <?php if ($jumlah_pending > 0): ?>
                <span class="badge badge-notif"><?= $jumlah_pending ?></span>
            <?php endif; ?>
        </a>
        <a href="prioritas_pemeliharaan.php" class="list-group-item <?= $cur_page === 'prioritas_pemeliharaan.php' ? 'active' : '' ?>">
            <div><i class="bi bi-shield-fill-check text-warning"></i> Prioritas Alkes Kritis</div>
        </a>
        <a href="monitoring_pemeliharaan.php" class="list-group-item <?= in_array($cur_page, ['monitoring_pemeliharaan.php', 'logbook_aset.php']) ? 'active' : '' ?>">
            <div><i class="bi bi-activity text-info"></i> Monitoring Pemeliharaan</div>
        </a>
        <a href="perawatan.php" class="list-group-item <?= in_array($cur_page, ['perawatan.php', 'perawatan_tambah.php', 'perawatan_edit.php']) ? 'active' : '' ?>">
            <div><i class="bi bi-tools"></i> Servis & Kalibrasi</div>
        </a>
        <a href="kerusakan.php" class="list-group-item <?= in_array($cur_page, ['kerusakan.php', 'kerusakan_tambah.php', 'kerusakan_edit.php']) ? 'active' : '' ?>">
            <div><i class="bi bi-exclamation-triangle"></i> Laporan Kerusakan</div>
        </a>
        <a href="analisis_aset.php" class="list-group-item <?= $cur_page === 'analisis_aset.php' ? 'active' : '' ?>">
            <div><i class="bi bi-graph-up-arrow"></i> Analisis & Anggaran Aset</div>
        </a>

        <div class="menu-category">Manajemen Infrastruktur (Gedung & Kamar)</div>
        <a href="infrastruktur.php" class="list-group-item <?= in_array($cur_page, ['infrastruktur.php', 'infrastruktur_tambah.php', 'infrastruktur_edit.php', 'infrastruktur_detail.php']) ? 'active' : '' ?>">
            <div><i class="bi bi-building"></i> Data Gedung & Fasilitas</div>
        </a>
        <a href="sebaran_aset.php" class="list-group-item <?= $cur_page === 'sebaran_aset.php' ? 'active' : '' ?>">
            <div><i class="bi bi-diagram-3"></i> Sebaran Aset Ruangan (KIR)</div>
        </a>
        <a href="aset_pindah.php" class="list-group-item <?= in_array($cur_page, ['aset_pindah.php', 'aset_pindah_edit.php']) ? 'active' : '' ?>">
            <div><i class="bi bi-signpost-split"></i> Mutasi / Pindah Ruangan</div>
        </a>

        <div class="menu-category">Audit Fisik & Akuntansi</div>
        <a href="audit_fisik.php" class="list-group-item <?= in_array($cur_page, ['audit_fisik.php', 'audit_tambah.php', 'audit_edit.php']) ? 'active' : '' ?>">
            <div><i class="bi bi-clipboard-check"></i> Audit Fisik Berkala</div>
        </a>
        <a href="stock_opname.php" class="list-group-item <?= in_array($cur_page, ['stock_opname.php', 'stock_opname_tambah.php']) ? 'active' : '' ?>">
            <div><i class="bi bi-clipboard2-check"></i> Stock Opname Aset</div>
        </a>

        <?php if ($role === 'admin') : ?>
            <div class="menu-category">Pengaturan Sistem</div>
            <a href="user.php" class="list-group-item <?= in_array($cur_page, ['user.php', 'add_user.php', 'edit_user.php']) ? 'active' : '' ?>">
                <div><i class="bi bi-people"></i> Manajemen User</div>
            </a>
            <a href="laporan.php" class="list-group-item <?= (strpos($cur_page, 'laporan') !== false && !in_array($cur_page, ['laporan_stock_opname.php', 'laporan_penyusutan_periode.php', 'laporan_infrastruktur.php'])) ? 'active' : '' ?>">
                <div><i class="bi bi-file-earmark-bar-graph"></i> Laporan</div>
            </a>
        <?php endif; ?>

        <a href="../logout.php" class="list-group-item logout-item" onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem SIMARIS?')">
            <div><i class="bi bi-box-arrow-right"></i> Logout</div>
        </a>
    </div>
</div>