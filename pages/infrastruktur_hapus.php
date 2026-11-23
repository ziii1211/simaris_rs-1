<?php
session_start();

if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../login.php");
    exit;
}

include(__DIR__ . '/../config/koneksi.php');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $delete = mysqli_query($koneksi, "DELETE FROM infrastruktur WHERE id_infrastruktur = $id");
}

header("Location: infrastruktur.php?pesan=hapus_sukses");
exit;
