<?php
// absen/web/proses_apel.php
// Handler POST: validasi & simpan presensi apel ke database

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hanya menerima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: absen_apel.php");
    exit;
}

// Enforce CSRF token
enforceCsrfCheck();

// Ambil input form
$user_id      = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
$nama         = trim($_POST['nama'] ?? '');
$jabatan      = trim($_POST['jabatan'] ?? '');
$sesi         = strtolower(trim($_POST['sesi'] ?? ''));
$tanda_tangan = trim($_POST['tanda_tangan'] ?? '');

$tanggal = date('Y-m-d');
$waktu   = date('H:i:s');

// Helper redirect dengan error
function redirectWithError($msg, $oldData) {
    $_SESSION['flash_error'] = $msg;
    $_SESSION['flash_old'] = $oldData;
    header("Location: absen_apel.php");
    exit;
}

// Validasi field wajib
if ($nama === '' || $jabatan === '' || $sesi === '' || $tanda_tangan === '') {
    redirectWithError("Semua field bertanda bintang (*) dan tanda tangan wajib diisi!", $_POST);
}

// Validasi sesi
if (!in_array($sesi, ['pagi', 'sore'], true)) {
    redirectWithError("Sesi apel tidak valid. Pilih 'pagi' atau 'sore'.", $_POST);
}

// Validasi hari dan jam apel (sesuai kebijakan SOP instansi)
// Apel Pagi: Senin (1) jam 07:00 - 08:30 WIB
// Apel Sore: Jumat (5) jam 16:00 - 17:30 WIB
$dayOfWeek = (int)date('N');

if ($sesi === 'pagi') {
    if ($dayOfWeek !== 1) {
        redirectWithError("Presensi Apel Pagi hanya dapat dilakukan pada hari Senin.", $_POST);
    }
    if ($waktu < '07:00:00' || $waktu > '08:30:00') {
        redirectWithError("Presensi Apel Pagi hanya dibuka pukul 07:00 - 08:30 WIB (Waktu server saat ini: $waktu WIB).", $_POST);
    }
} elseif ($sesi === 'sore') {
    if ($dayOfWeek !== 5) {
        redirectWithError("Presensi Apel Sore hanya dapat dilakukan pada hari Jumat.", $_POST);
    }
    if ($waktu < '16:00:00' || $waktu > '17:30:00') {
        redirectWithError("Presensi Apel Sore hanya dibuka pukul 16:00 - 17:30 WIB (Waktu server saat ini: $waktu WIB).", $_POST);
    }
}

// Validasi tanda tangan base64
if (strpos($tanda_tangan, 'data:image/png;base64,') !== 0 && strpos($tanda_tangan, 'data:image/jpeg;base64,') !== 0) {
    redirectWithError("Format tanda tangan digital tidak valid.", $_POST);
}

try {
    // Cek duplikasi absensi jika user_id diketahui
    if ($user_id !== null && $user_id > 0) {
        $stmtCek = $pdo->prepare("SELECT id FROM presensi_apel WHERE user_id = ? AND tanggal = ? AND sesi = ? LIMIT 1");
        $stmtCek->execute([$user_id, $tanggal, $sesi]);
        if ($stmtCek->fetch()) {
            redirectWithError("Anda sudah melakukan presensi apel " . strtoupper($sesi) . " pada hari ini ($tanggal).", $_POST);
        }
    } else {
        // Cek duplikasi berdasarkan nama dan jabatan jika tanpa user_id
        $stmtCekNama = $pdo->prepare("SELECT id FROM presensi_apel WHERE LOWER(nama) = LOWER(?) AND tanggal = ? AND sesi = ? LIMIT 1");
        $stmtCekNama->execute([$nama, $tanggal, $sesi]);
        if ($stmtCekNama->fetch()) {
            redirectWithError("Pegawai dengan nama '$nama' sudah tercatat presensi apel " . strtoupper($sesi) . " hari ini.", $_POST);
        }
    }

    // Insert ke tabel presensi_apel
    $stmt = $pdo->prepare("INSERT INTO presensi_apel (user_id, nama, jabatan, tanda_tangan, sesi, tanggal, waktu) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $nama, $jabatan, $tanda_tangan, $sesi, $tanggal, $waktu]);

    // Simpan data sukses ke session untuk ditampilkan di success.php
    $_SESSION['absen_success'] = [
        'tipe'         => 'apel',
        'nama'         => $nama,
        'jabatan'      => $jabatan,
        'detail'       => 'Apel ' . ucfirst($sesi),
        'tanggal'      => $tanggal,
        'waktu'        => $waktu,
        'tanda_tangan' => $tanda_tangan
    ];

    header("Location: success.php");
    exit;

} catch (Exception $e) {
    error_log("[proses_apel] Error: " . $e->getMessage());
    redirectWithError("Terjadi kesalahan sistem saat menyimpan presensi: " . $e->getMessage(), $_POST);
}
