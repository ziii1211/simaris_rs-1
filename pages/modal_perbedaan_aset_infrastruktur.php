<!-- MODAL MATRIKS PERBEDAAN ASET VS INFRASTRUKTUR -->
<div class="modal fade" id="modalMatriksPerbedaan" tabindex="-1" aria-labelledby="modalMatriksPerbedaanLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #1e3a8a, #0d9488); border-top-left-radius: 16px; border-top-right-radius: 16px;">
                <div>
                    <h5 class="modal-title fw-bold" id="modalMatriksPerbedaanLabel">
                        <i class="bi bi-diagram-3-fill me-2"></i> MATRIKS PERBEDAAN MANAJEMEN ASET VS MANAJEMEN INFRASTRUKTUR
                    </h5>
                    <small class="text-white-50">Kajian Konseptual Berdasarkan Standar Permenkes No. 24/2016 & Standar Akreditasi Kemenkes RI (STARKES MFK)</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" style="background-color: #f8fafc;">

                <!-- 1. DIAGRAM ANALOGI: WADAH VS ISI -->
                <div class="card border-0 shadow-sm rounded-4 mb-4" style="background: linear-gradient(135deg, #eff6ff, #f0fdf4); border: 1px solid #bfdbfe !important;">
                    <div class="card-body p-3 p-md-4">
                        <div class="row align-items-center g-3">
                            <div class="col-md-5 text-center border-end-md">
                                <div class="badge bg-primary px-3 py-2 rounded-pill mb-2 fw-bold fs-6">
                                    <i class="bi bi-building me-1"></i> INFRASTRUKTUR (PRASARANA)
                                </div>
                                <h5 class="fw-bold text-dark m-0">"WADAH / FASILITAS FISIK"</h5>
                                <p class="text-muted small mt-1 mb-0">
                                    Gedung Utama, Ruang Rawat Inap, Ruang ICU, Kamar Operasi, Jaringan Listrik & Gas Medis Sentral.
                                </p>
                            </div>
                            <div class="col-md-2 text-center">
                                <div class="d-inline-flex align-items-center justify-content-center bg-white shadow-sm rounded-circle p-3 text-success fs-3">
                                    <i class="bi bi-arrow-down-up d-none d-md-block"></i>
                                    <i class="bi bi-arrow-down d-md-none"></i>
                                </div>
                                <div class="small fw-bold text-secondary mt-1">Menaungi / Menampung</div>
                            </div>
                            <div class="col-md-5 text-center">
                                <div class="badge bg-danger px-3 py-2 rounded-pill mb-2 fw-bold fs-6">
                                    <i class="bi bi-box-seam me-1"></i> ASET (SARANA & ALKES)
                                </div>
                                <h5 class="fw-bold text-dark m-0">"ISI / PERALATAN OPERASIONAL"</h5>
                                <p class="text-muted small mt-1 mb-0">
                                    Defibrillator, Bed Pasien, Mesin USG 4D, EKG 12 Lead, Autoclave, Komputer Kasir, AC.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. TABEL KOMPARASI 7 DIMENSI -->
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="fw-bold text-dark m-0"><i class="bi bi-table text-primary me-2"></i> Perbandingan 7 Dimensi Manajemen Rumah Sakit</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0" style="font-size: 0.88rem;">
                            <thead class="table-light text-center">
                                <tr>
                                    <th width="180">Dimensi Analisis</th>
                                    <th width="40%" class="text-primary"><i class="bi bi-box-seam me-1"></i> Manajemen Aset (Sarana & Alkes)</th>
                                    <th width="40%" class="text-teal"><i class="bi bi-building me-1"></i> Manajemen Infrastruktur (Prasarana)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="fw-bold bg-light">1. Definisi Fisik</td>
                                    <td><strong>Barang Bergerak (*Movable Property*)</strong> yang digunakan secara langsung untuk tindakan medis diagnostik, kuratif, maupun administrasi.</td>
                                    <td><strong>Fasilitas Tetap / Bangunan (*Immovable / Fixed Assets*)</strong> yang menjadi tempat bernaung dan berlangsungnya proses pelayanan medis.</td>
                                </tr>
                                <tr>
                                    <td class="fw-bold bg-light">2. Karakteristik Mobilitas</td>
                                    <td><span class="badge bg-success bg-opacity-10 text-success border border-success">Sangat Fleksibel</span> Dapat dimutasi antar ruangan, dipindahkan ke ruang bedah lain, atau dipinjam antar poli.</td>
                                    <td><span class="badge bg-secondary bg-opacity-10 text-secondary border">Statis / Tetap</span> Tidak dapat dipindahkan lokasinya secara fisik (permanen di denah RS).</td>
                                </tr>
                                <tr>
                                    <td class="fw-bold bg-light">3. Alur Kerja Sistem (Workflow)</td>
                                    <td>
                                        <ul class="mb-0 ps-3">
                                            <li>Pengadaan & Registrasi Barcode/QR</li>
                                            <li>Penempatan & <strong>Peminjaman Alat</strong></li>
                                            <li>Pencatatan Riwayat Kerusakan & Perbaikan</li>
                                            <li>Penyusutan Nilai Buku Periodik</li>
                                        </ul>
                                    </td>
                                    <td>
                                        <ul class="mb-0 ps-3">
                                            <li>Pembangunan / Renovasi Gedung</li>
                                            <li>Penetapan Zonasi & Kapasitas Tempat Tidur (Bed)</li>
                                            <li>Pemeriksaan Kelaikan Fisik Bangunan (SLF)</li>
                                            <li>Wadah Penempatan Inventaris (KIR)</li>
                                        </ul>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold bg-light">4. Ruang Lingkup Pemeliharaan</td>
                                    <td>
                                        <strong>Kalibrasi Teknis & Elektromedis:</strong> Kalibrasi frekuensi, uji arus bocor (*leakage current*), penggantian sparepart (baterai, sensor, probe).
                                    </td>
                                    <td>
                                        <strong>Fasilitas & Sipil:</strong> Perbaikan atap/dinding, sistem tata udara (HEPA Filter tekanan negatif ruang OK/ICU), sanitasi IPAL, instalasi gas medis pipa sentral.
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold bg-light">5. Pihak Penanggung Jawab</td>
                                    <td><strong>Teknisi Elektromedis (ATEM)</strong> untuk fungsi teknis, dan <strong>Dokter / Perawat Operator</strong> untuk penggunaan alat.</td>
                                    <td><strong>IPSRS (Instalasi Pemeliharaan Sarana Rumah Sakit)</strong>, Komite K3RS (Keselamatan Kerja), dan Kepala Ruangan Pelayanan.</td>
                                </tr>
                                <tr>
                                    <td class="fw-bold bg-light">6. Satuan Ukur & Kapasitas</td>
                                    <td>Jumlah unit (buah/set), umur ekonomis (tahun), kapasitas baterai / daya listrik (Watt).</td>
                                    <td>Luas bangunan ($m^2$), kapasitas tempat tidur (*bed capacity*), lantai gedung, tingkat sterilisasi zona.</td>
                                </tr>
                                <tr>
                                    <td class="fw-bold bg-light">7. Hak Akses & Operasional</td>
                                    <td>Dapat diajukan dalam form <em>Peminjaman Alat</em> dan form <em>Pelaporan Kerusakan</em>.</td>
                                    <td>Tersedia dalam form <em>Pendataan Gedung/Kamar</em> dan pencetakan <em>Kartu Inventaris Ruangan (KIR)</em>.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 3. KESIMPULAN RINGKAS UNTUK SIDANG -->
                <div class="alert alert-success d-flex align-items-center mb-0 rounded-3">
                    <i class="bi bi-lightbulb-fill fs-3 me-3 text-warning"></i>
                    <div>
                        <strong class="d-block mb-1">Catatan Kunci untuk Presentasi Sidang:</strong>
                        <span class="small text-secondary">
                            "Infrastruktur tidak dapat dipinjamkan sebagaimana alkes di menu peminjaman, melainkan berfungsi sebagai wadah penempatan dan pemantauan kapasitas. Sebaliknya, aset medis tidak memiliki atribut kapasitas bed atau luas meter persegi, melainkan memiliki kode QR unik, riwayat kalibrasi elektromedis, dan penyusutan nilai buku."
                        </span>
                    </div>
                </div>

            </div>
            <div class="modal-footer bg-white border-top">
                <button type="button" class="btn btn-secondary px-4 fw-semibold" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
