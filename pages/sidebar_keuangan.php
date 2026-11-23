<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$nama_user = $_SESSION['nama_pengguna'] ?? 'Staf Keuangan';
$jabatan_user = $_SESSION['jabatan'] ?? 'Bagian Keuangan';
?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

    #sidebar-wrapper {
        width: 260px;
        background: linear-gradient(180deg, #1e3a8a, #0d9488);
        color: #fff;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        font-family: 'Inter', sans-serif;
        box-shadow: 4px 0 10px rgba(0, 0, 0, 0.05);
    }

    .sidebar-heading {
        padding: 2rem 1.5rem 1rem 1.5rem;
        font-size: 1.1rem;
        font-weight: 700;
        text-align: center;
        letter-spacing: 0.5px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
    }

    .sidebar-logo {
        width: 85px;
        height: 85px;
        object-fit: cover;
        border-radius: 50%;
        border: 2px solid rgba(255, 255, 255, 0.5);
        background-color: #fff;
        padding: 2px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    .sidebar-role-badge {
        font-size: 0.72rem;
        background: rgba(255, 255, 255, 0.2);
        padding: 3px 10px;
        border-radius: 20px;
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    .menu-category {
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        color: rgba(255, 255, 255, 0.6);
        padding: 1.2rem 15px 0.4rem 15px;
    }

    .list-group {
        padding: 0 10px;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .list-group-item {
        background: transparent !important;
        color: rgba(255, 255, 255, 0.88) !important;
        border: none !important;
        margin: 2px 0;
        padding: 10px 14px;
        border-radius: 8px !important;
        display: flex;
        align-items: center;
        justify-content: space-between;
        text-decoration: none;
        font-size: 0.88rem;
        font-weight: 500;
        transition: all 0.2s ease-in-out;
    }

    .list-group-item:hover,
    .list-group-item.active {
        background: rgba(255, 255, 255, 0.25) !important;
        color: #fff !important;
        padding-left: 20px;
        font-weight: 600;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
    }

    .list-group-item div {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .logout-item {
        margin-top: auto !important;
        margin-bottom: 15px;
        background: rgba(239, 68, 68, 0.15) !important;
        color: #ffa3a3 !important;
        border: 1px solid rgba(239, 68, 68, 0.3) !important;
        font-weight: 600;
    }

    .logout-item:hover {
        background: #ef4444 !important;
        color: #fff !important;
        padding-left: 20px;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
    }
</style>

<?php $cur_page = basename($_SERVER['PHP_SELF'] ?? ''); ?>

<div id="sidebar-wrapper">
    <div class="sidebar-heading">
        <img src="../assets/img/logo.png" alt="Logo RS" class="sidebar-logo">
        <span class="mt-1">SIMARIS RS</span>
        <span class="sidebar-role-badge"><i class="bi bi-wallet2 me-1"></i>Bagian Keuangan</span>
    </div>

    <div class="list-group list-group-flush">
        <div class="menu-category">Utama</div>
        <a href="dashboard_keuangan.php" class="list-group-item <?= $cur_page === 'dashboard_keuangan.php' ? 'active' : '' ?>">
            <div><i class="bi bi-speedometer2"></i> Dashboard Keuangan</div>
        </a>

        <div class="menu-category">Manajemen Aset (Sarana & Alkes)</div>
        <a href="laporan_nilai.php" class="list-group-item <?= $cur_page === 'laporan_nilai.php' ? 'active' : '' ?>">
            <div><i class="bi bi-cash-stack"></i> Nilai & Depresiasi Aset</div>
        </a>
        <a href="analisis_aset.php" class="list-group-item <?= $cur_page === 'analisis_aset.php' ? 'active' : '' ?>">
            <div><i class="bi bi-graph-up-arrow"></i> Analisis & Anggaran Aset</div>
        </a>
        <a href="laporan_penyusutan_periode.php" class="list-group-item <?= $cur_page === 'laporan_penyusutan_periode.php' ? 'active' : '' ?>">
            <div><i class="bi bi-graph-down-arrow"></i> Penyusutan per Periode</div>
        </a>
        <a href="prioritas_pemeliharaan.php" class="list-group-item <?= $cur_page === 'prioritas_pemeliharaan.php' ? 'active' : '' ?>">
            <div><i class="bi bi-shield-fill-check text-warning"></i> Prioritas Pemeliharaan</div>
        </a>
        <a href="monitoring_pemeliharaan.php" class="list-group-item <?= $cur_page === 'monitoring_pemeliharaan.php' ? 'active' : '' ?>">
            <div><i class="bi bi-activity text-info"></i> Monitoring Pemeliharaan</div>
        </a>

        <div class="menu-category">Manajemen Infrastruktur (Prasarana)</div>
        <a href="infrastruktur.php" class="list-group-item <?= in_array($cur_page, ['infrastruktur.php', 'infrastruktur_detail.php']) ? 'active' : '' ?>">
            <div><i class="bi bi-building"></i> Data Gedung & Fasilitas</div>
        </a>
        <a href="sebaran_aset.php" class="list-group-item <?= $cur_page === 'sebaran_aset.php' ? 'active' : '' ?>">
            <div><i class="bi bi-diagram-3"></i> Sebaran Aset Ruangan (KIR)</div>
        </a>

        <div class="menu-category">Audit & Stock Opname</div>
        <a href="laporan_stock_opname.php" class="list-group-item <?= $cur_page === 'laporan_stock_opname.php' ? 'active' : '' ?>">
            <div><i class="bi bi-clipboard2-check"></i> Hasil Stock Opname</div>
        </a>

        <a href="../logout.php" class="list-group-item logout-item" onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem SIMARIS?')">
            <div><i class="bi bi-box-arrow-right"></i> Logout</div>
        </a>
    </div>
</div>
