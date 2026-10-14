<?php
// absen/web/logout.php
// Hapus session pegawai dan redirect ke halaman login

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hapus hanya variabel session pegawai (jangan ganggu session admin)
unset(
    $_SESSION['pegawai_logged_in'],
    $_SESSION['pegawai_id'],
    $_SESSION['pegawai_nama'],
    $_SESSION['pegawai_jabatan'],
    $_SESSION['pegawai_nip'],
    $_SESSION['pegawai_username']
);

session_destroy();

header("Location: login.php");
exit;
