<?php
// absen/web/proses_login.php
// Handler POST: validasi login pegawai ke tabel users

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Jika sudah login, redirect langsung
if (!empty($_SESSION['pegawai_logged_in']) && $_SESSION['pegawai_logged_in'] === true) {
    header("Location: index.php");
    exit;
}

// Hanya terima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

// Enforce CSRF
enforceCsrfCheck();

$username = trim(isset($_POST['username']) ? $_POST['username'] : '');
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Helper redirect error
function redirectLoginError($msg, $oldData = array()) {
    $_SESSION['flash_error'] = $msg;
    $_SESSION['flash_old']   = $oldData;
    header("Location: login.php");
    exit;
}

// Validasi input kosong
if ($username === '' || $password === '') {
    redirectLoginError("Username dan password wajib diisi.", $_POST);
}

try {
    // Cari pegawai berdasarkan username (case-insensitive)
    $stmt = $pdo->prepare("SELECT id, nama, jabatan, nip, username, password, is_active FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Username tidak ditemukan — jangan bocorkan info spesifik
        redirectLoginError("Username atau password salah. Silakan periksa kembali.", array('username' => $username));
    }

    // Cek apakah akun aktif
    if ((int)$user['is_active'] === 0) {
        redirectLoginError("Akun Anda telah dinonaktifkan. Hubungi administrator untuk informasi lebih lanjut.", array('username' => $username));
    }

    // Verifikasi password
    if (!password_verify($password, $user['password'])) {
        redirectLoginError("Username atau password salah. Silakan periksa kembali.", array('username' => $username));
    }

    // Login berhasil — regenerate session ID untuk keamanan
    session_regenerate_id(true);

    // Simpan data pegawai ke session
    $_SESSION['pegawai_logged_in'] = true;
    $_SESSION['pegawai_id']        = (int)$user['id'];
    $_SESSION['pegawai_nama']      = $user['nama'];
    $_SESSION['pegawai_jabatan']   = $user['jabatan'];
    $_SESSION['pegawai_nip']       = isset($user['nip']) ? $user['nip'] : '';
    $_SESSION['pegawai_username']  = $user['username'];

    // Redirect ke portal
    header("Location: index.php");
    exit;

} catch (Exception $e) {
    error_log("[proses_login] Error: " . $e->getMessage());
    redirectLoginError("Terjadi kesalahan sistem. Silakan coba lagi.");
}
