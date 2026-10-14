<?php
// absen/web/proses_rapat.php
// Handler POST: validasi PIN & simpan presensi rapat ke tabel presensi

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hanya menerima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: absen_rapat.php");
    exit;
}

// Enforce CSRF check
enforceCsrfCheck();

// Ambil input form
$user_id      = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
$acara_id     = !empty($_POST['acara_id']) ? (int)$_POST['acara_id'] : 0;
$nama         = trim($_POST['nama'] ?? '');
$jabatan      = trim($_POST['jabatan'] ?? '');
$pin          = trim($_POST['pin'] ?? '');
$tanda_tangan = trim($_POST['tanda_tangan'] ?? '');

// Helper redirect error
function redirectWithError($msg, $oldData) {
    $_SESSION['flash_error'] = $msg;
    $_SESSION['flash_old'] = $oldData;
    header("Location: absen_rapat.php");
    exit;
}

// Validasi field wajib
if ($acara_id <= 0 || $nama === '' || $jabatan === '' || $pin === '' || $tanda_tangan === '') {
    redirectWithError("Semua field bertanda bintang (*) dan tanda tangan wajib diisi!", $_POST);
}

// Validasi tanda tangan base64
if (strpos($tanda_tangan, 'data:image/png;base64,') !== 0 && strpos($tanda_tangan, 'data:image/jpeg;base64,') !== 0) {
    redirectWithError("Format tanda tangan digital tidak valid.", $_POST);
}

try {
    // 1. Ambil data acara
    $stmtAcara = $pdo->prepare("SELECT id, nama_acara, pin_acara, status FROM acara WHERE id = ? LIMIT 1");
    $stmtAcara->execute([$acara_id]);
    $acara = $stmtAcara->fetch(PDO::FETCH_ASSOC);

    if (!$acara || $acara['status'] !== 'BUKA') {
        redirectWithError("Rapat/Acara tidak ditemukan atau sudah ditutup oleh panitia.", $_POST);
    }

    // 2. Rate limiting PIN salah berdasarkan IP (5x dalam 15 menit)
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
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
            redirectWithError("Terlalu banyak percobaan PIN salah. Mohon tunggu {$remaining} menit lagi.", $_POST);
        }
    }

    // 3. Verifikasi PIN Acara
    if ($pin !== $acara['pin_acara']) {
        // Catat kegagalan
        $pdo->prepare("INSERT INTO pin_attempts (ip_address, attempts) VALUES (?, 1) ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()")->execute([$client_ip]);
        redirectWithError("Kode PIN Rapat tidak valid! Silakan tanyakan kepada panitia di lokasi.", $_POST);
    }

    // Reset percobaan jika PIN benar
    $pdo->prepare("DELETE FROM pin_attempts WHERE ip_address = ?")->execute([$client_ip]);

    // 4. Cek duplikasi absensi pada rapat yang sama
    if ($user_id !== null && $user_id > 0) {
        $stmtCek = $pdo->prepare("SELECT id FROM presensi WHERE acara_id = ? AND user_id = ? LIMIT 1");
        $stmtCek->execute([$acara_id, $user_id]);
        if ($stmtCek->fetch()) {
            redirectWithError("Anda sudah terdaftar dan menandatangani presensi untuk rapat ini.", $_POST);
        }
    } else {
        $stmtCekNama = $pdo->prepare("SELECT id FROM presensi WHERE acara_id = ? AND LOWER(nama) = LOWER(?) LIMIT 1");
        $stmtCekNama->execute([$acara_id, $nama]);
        if ($stmtCekNama->fetch()) {
            redirectWithError("Peserta atas nama '$nama' sudah tercatat dalam daftar hadir rapat ini.", $_POST);
        }
    }

    // 5. Simpan presensi rapat
    $stmtInsert = $pdo->prepare("INSERT INTO presensi (acara_id, user_id, nama, jabatan, tanda_tangan) VALUES (?, ?, ?, ?, ?)");
    $stmtInsert->execute([$acara_id, $user_id, $nama, $jabatan, $tanda_tangan]);

    // 6. Simpan session sukses
    $_SESSION['absen_success'] = [
        'tipe'         => 'rapat',
        'nama'         => $nama,
        'jabatan'      => $jabatan,
        'detail'       => $acara['nama_acara'],
        'tanggal'      => date('Y-m-d'),
        'waktu'        => date('H:i:s'),
        'tanda_tangan' => $tanda_tangan
    ];

    header("Location: success.php");
    exit;

} catch (Exception $e) {
    error_log("[proses_rapat] Error: " . $e->getMessage());
    redirectWithError("Terjadi kesalahan sistem saat menyimpan presensi: " . $e->getMessage(), $_POST);
}
