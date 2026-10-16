<?php
// absen/web/auth_check.php
// Helper: Pastikan pegawai sudah login sebelum akses halaman web portal

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['pegawai_logged_in']) || $_SESSION['pegawai_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Helper: ambil data pegawai yang sedang login
function getPegawaiSession() {
    return array(
        'id'       => (int)(isset($_SESSION['pegawai_id']) ? $_SESSION['pegawai_id'] : 0),
        'nama'     => isset($_SESSION['pegawai_nama']) ? $_SESSION['pegawai_nama'] : '',
        'jabatan'  => isset($_SESSION['pegawai_jabatan']) ? $_SESSION['pegawai_jabatan'] : '',
        'nip'      => isset($_SESSION['pegawai_nip']) ? $_SESSION['pegawai_nip'] : '',
        'username' => isset($_SESSION['pegawai_username']) ? $_SESSION['pegawai_username'] : '',
    );
}
