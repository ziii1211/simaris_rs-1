<?php
session_start();
if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../login.php");
    exit;
}

include(__DIR__ . '/../config/koneksi.php');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    mysqli_query($koneksi, "DELETE FROM stock_opname WHERE id_opname = $id");
}

header("Location: stock_opname.php?msg=sukses_hapus");
exit;
