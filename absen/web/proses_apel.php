<?php
// absen/web/proses_apel.php
// Handler POST: validasi & simpan presensi apel (data pegawai dari SESSION)

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/auth_check.php';

$pegawai = getPegawaiSession();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: absen_apel.php");
    exit;
}

enforceCsrfCheck();

// Data pegawai SELALU dari session — bukan dari POST
$user_id  = $pegawai['id'];
$nama     = $pegawai['nama'];
$jabatan  = $pegawai['jabatan'];

// Hanya sesi & tanda tangan yang dari POST
$sesi         = strtolower(trim(isset($_POST['sesi']) ? $_POST['sesi'] : ''));
$tanda_tangan = trim(isset($_POST['tanda_tangan']) ? $_POST['tanda_tangan'] : '');

$tanggal = date('Y-m-d');
$waktu   = date('H:i:s');

function redirectWithError($msg, $oldData = array()) {
    $_SESSION['flash_error'] = $msg;
    $_SESSION['flash_old']   = $oldData;
    header("Location: absen_apel.php");
    exit;
}

// Validasi
if ($sesi === '' || $tanda_tangan === '') {
    redirectWithError("Pilih sesi apel dan pastikan tanda tangan sudah dibubuhkan.", $_POST);
}
if (!in_array($sesi, ['pagi', 'sore'], true)) {
    redirectWithError("Sesi apel tidak valid.", $_POST);
}
if (strpos($tanda_tangan, 'data:image/') !== 0) {
    redirectWithError("Format tanda tangan tidak valid.", $_POST);
}

// Validasi hari dan jam apel
$dayOfWeek = (int)date('N');
if ($sesi === 'pagi') {
    if ($dayOfWeek !== 1) {
        redirectWithError("Presensi Apel Pagi hanya dapat dilakukan pada hari Senin.", $_POST);
    }
    if ($waktu < '07:00:00' || $waktu > '08:30:00') {
        redirectWithError("Presensi Apel Pagi hanya dibuka pukul 07:00 - 08:30 WIB (waktu server: $waktu).", $_POST);
    }
} elseif ($sesi === 'sore') {
    if ($dayOfWeek !== 5) {
        redirectWithError("Presensi Apel Sore hanya dapat dilakukan pada hari Jumat.", $_POST);
    }
    if ($waktu < '16:00:00' || $waktu > '17:30:00') {
        redirectWithError("Presensi Apel Sore hanya dibuka pukul 16:00 - 17:30 WIB (waktu server: $waktu).", $_POST);
    }
}

try {
    // Cek duplikasi untuk user ini pada sesi & tanggal yang sama
    $stmtCek = $pdo->prepare("SELECT id FROM presensi_apel WHERE user_id = ? AND tanggal = ? AND sesi = ? LIMIT 1");
    $stmtCek->execute([$user_id, $tanggal, $sesi]);
    if ($stmtCek->fetch()) {
        redirectWithError("Anda sudah melakukan presensi apel " . strtoupper($sesi) . " pada hari ini ($tanggal).", $_POST);
    }

    // Insert
    $stmt = $pdo->prepare("INSERT INTO presensi_apel (user_id, nama, jabatan, tanda_tangan, sesi, tanggal, waktu) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $nama, $jabatan, $tanda_tangan, $sesi, $tanggal, $waktu]);

    $_SESSION['absen_success'] = array(
        'tipe'         => 'apel',
        'nama'         => $nama,
        'jabatan'      => $jabatan,
        'detail'       => 'Apel ' . ucfirst($sesi),
        'tanggal'      => $tanggal,
        'waktu'        => $waktu,
        'tanda_tangan' => $tanda_tangan,
    );

    header("Location: success.php");
    exit;

} catch (Exception $e) {
    error_log("[proses_apel] " . $e->getMessage());
    redirectWithError("Terjadi kesalahan sistem: " . $e->getMessage(), $_POST);
}
