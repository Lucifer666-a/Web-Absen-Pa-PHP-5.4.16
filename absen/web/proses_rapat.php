<?php
// absen/web/proses_rapat.php
// Handler POST: validasi PIN & simpan presensi rapat (data pegawai dari SESSION)

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/auth_check.php';

$pegawai = getPegawaiSession();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: absen_rapat.php");
    exit;
}

enforceCsrfCheck();

// Data pegawai SELALU dari session
$user_id = $pegawai['id'];
$nama    = $pegawai['nama'];
$jabatan = $pegawai['jabatan'];

// Hanya acara_id, pin, tanda_tangan dari POST
$acara_id     = !empty($_POST['acara_id']) ? (int)$_POST['acara_id'] : 0;
$pin          = trim(isset($_POST['pin']) ? $_POST['pin'] : '');
$tanda_tangan = trim(isset($_POST['tanda_tangan']) ? $_POST['tanda_tangan'] : '');

function redirectWithError($msg, $oldData = array()) {
    $_SESSION['flash_error'] = $msg;
    $_SESSION['flash_old']   = $oldData;
    header("Location: absen_rapat.php");
    exit;
}

// Validasi field wajib
if ($acara_id <= 0 || $pin === '' || $tanda_tangan === '') {
    redirectWithError("Pilih kegiatan rapat, masukkan PIN, dan bubuhkan tanda tangan.", $_POST);
}

if (strpos($tanda_tangan, 'data:image/') !== 0) {
    redirectWithError("Format tanda tangan tidak valid.", $_POST);
}

try {
    // Ambil data acara
    $stmtAcara = $pdo->prepare("SELECT id, nama_acara, pin_acara, status FROM acara WHERE id = ? LIMIT 1");
    $stmtAcara->execute([$acara_id]);
    $acara = $stmtAcara->fetch(PDO::FETCH_ASSOC);

    if (!$acara || $acara['status'] !== 'BUKA') {
        redirectWithError("Rapat tidak ditemukan atau sudah ditutup.", $_POST);
    }

    // Rate limiting PIN salah (5x dalam 15 menit) berdasarkan IP
    $client_ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
    $stmtAttempt = $pdo->prepare("SELECT attempts, TIMESTAMPDIFF(MINUTE, last_attempt, NOW()) AS minutes_ago FROM pin_attempts WHERE ip_address = ? LIMIT 1");
    $stmtAttempt->execute([$client_ip]);
    $attemptData = $stmtAttempt->fetch(PDO::FETCH_ASSOC);

    if ($attemptData) {
        if ((int)$attemptData['minutes_ago'] > 15) {
            $pdo->prepare("UPDATE pin_attempts SET attempts = 0 WHERE ip_address = ?")->execute([$client_ip]);
            $attempts = 0;
        } else {
            $attempts = (int)$attemptData['attempts'];
        }
        if ($attempts >= 5) {
            $remaining = 15 - (int)$attemptData['minutes_ago'];
            redirectWithError("Terlalu banyak percobaan PIN salah. Coba lagi dalam {$remaining} menit.", $_POST);
        }
    }

    // Verifikasi PIN
    if ($pin !== $acara['pin_acara']) {
        $pdo->prepare("INSERT INTO pin_attempts (ip_address, attempts) VALUES (?, 1) ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()")->execute([$client_ip]);
        redirectWithError("PIN Rapat tidak valid! Pastikan PIN yang Anda masukkan benar.", $_POST);
    }

    // Reset percobaan PIN
    $pdo->prepare("DELETE FROM pin_attempts WHERE ip_address = ?")->execute([$client_ip]);

    // Cek duplikasi: satu pegawai hanya bisa absen 1x per rapat
    $stmtCek = $pdo->prepare("SELECT id FROM presensi WHERE acara_id = ? AND user_id = ? LIMIT 1");
    $stmtCek->execute([$acara_id, $user_id]);
    if ($stmtCek->fetch()) {
        redirectWithError("Anda sudah tercatat hadir dalam rapat \"" . htmlspecialchars($acara['nama_acara'], ENT_QUOTES, 'UTF-8') . "\".", $_POST);
    }

    // Insert ke tabel presensi
    $stmt = $pdo->prepare("INSERT INTO presensi (acara_id, user_id, nama, jabatan, tanda_tangan) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(array($acara_id, $user_id, $nama, $jabatan, $tanda_tangan));

    $_SESSION['absen_success'] = array(
        'tipe'         => 'rapat',
        'nama'         => $nama,
        'jabatan'      => $jabatan,
        'detail'       => $acara['nama_acara'],
        'tanggal'      => date('Y-m-d'),
        'waktu'        => date('H:i:s'),
        'tanda_tangan' => $tanda_tangan,
    );

    header("Location: success.php");
    exit;

} catch (Exception $e) {
    error_log("[proses_rapat] " . $e->getMessage());
    redirectWithError("Terjadi kesalahan sistem: " . $e->getMessage(), $_POST);
}
