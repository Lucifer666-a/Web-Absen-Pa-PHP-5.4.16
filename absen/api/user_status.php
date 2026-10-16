<?php
// absen/api/user_status.php
// Endpoint REST API: Mengecek status absensi harian & riwayat absensi user untuk aplikasi Android

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

// Verifikasi JWT Bearer Token
$tokenPayload = verifyApiAuth($pdo, 'access');

// Ambil user_id dari GET, POST JSON, atau dari Token Payload
$user_id = 0;
if (isset($_GET['user_id'])) {
    $user_id = (int)$_GET['user_id'];
} elseif (isset($_GET['userId'])) {
    $user_id = (int)$_GET['userId'];
}

if ($user_id <= 0) {
    $rawInput = file_get_contents('php://input');
    $rawInput = ltrim($rawInput, "\xEF\xBB\xBF");
    $input    = json_decode($rawInput, true);
    if (is_array($input)) {
        if (isset($input['user_id'])) {
            $user_id = (int)$input['user_id'];
        } elseif (isset($input['userId'])) {
            $user_id = (int)$input['userId'];
        }
    }
}

if ($user_id <= 0 && isset($tokenPayload['user_id'])) {
    $user_id = (int)$tokenPayload['user_id'];
}

if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(array(
        'status'  => 'error',
        'message' => 'Parameter user_id tidak valid.'
    ));
    exit;
}

$today = date('Y-m-d');

try {
    // 1. Cek Profil User
    $stmtUser = $pdo->prepare("SELECT id, nip, nama, jabatan, username FROM users WHERE id = ? LIMIT 1");
    $stmtUser->execute(array($user_id));
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(array(
            'status'  => 'error',
            'message' => 'User tidak ditemukan.'
        ));
        exit;
    }

    // 2. Cek Status Apel Hari Ini
    $stmtApelPagi = $pdo->prepare("SELECT id, waktu, tanda_tangan FROM presensi_apel WHERE user_id = ? AND tanggal = ? AND sesi = 'pagi' LIMIT 1");
    $stmtApelPagi->execute(array($user_id, $today));
    $apelPagi = $stmtApelPagi->fetch(PDO::FETCH_ASSOC);

    $stmtApelSore = $pdo->prepare("SELECT id, waktu, tanda_tangan FROM presensi_apel WHERE user_id = ? AND tanggal = ? AND sesi = 'sore' LIMIT 1");
    $stmtApelSore->execute(array($user_id, $today));
    $apelSore = $stmtApelSore->fetch(PDO::FETCH_ASSOC);

    // 2.b Cek Status Absensi Harian Hari Ini (Check-in & Check-out)
    $stmtHarian = $pdo->prepare("SELECT waktu_checkin, waktu_checkout FROM absensi_harian WHERE user_id = ? AND tanggal = ? LIMIT 1");
    $stmtHarian->execute(array($user_id, $today));
    $absensiHarian = $stmtHarian->fetch(PDO::FETCH_ASSOC);

    $absensiHarianHariIni = array(
        'checkin'        => isset($absensiHarian['waktu_checkin']) ? $absensiHarian['waktu_checkin'] : null,
        'checkout'       => isset($absensiHarian['waktu_checkout']) ? $absensiHarian['waktu_checkout'] : null,
        'status_checkin' => isset($absensiHarian['status_checkin']) ? $absensiHarian['status_checkin'] : null
    );

    $statusHariIni = array(
        'tanggal'                => $today,
        'absensi_harian_hari_ini' => $absensiHarianHariIni,
        'apel_pagi'              => array(
            'sudah_absen'  => !empty($apelPagi),
            'waktu'        => isset($apelPagi['waktu']) ? $apelPagi['waktu'] : null,
            'tanda_tangan' => isset($apelPagi['tanda_tangan']) ? $apelPagi['tanda_tangan'] : null
        ),
        'apel_sore'              => array(
            'sudah_absen'  => !empty($apelSore),
            'waktu'        => isset($apelSore['waktu']) ? $apelSore['waktu'] : null,
            'tanda_tangan' => isset($apelSore['tanda_tangan']) ? $apelSore['tanda_tangan'] : null
        )
    );

    // 3. Ambil Riwayat Keseluruhan (Apel + Rapat + Harian) milik user_id
    // Query Harian
    $stmtRiwayatHarian = $pdo->prepare("
        SELECT 'harian' AS jenis, 
               'Absen Harian' AS judul,
               tanggal, 
               COALESCE(waktu_checkin, waktu_checkout) AS waktu, 
               NULL AS tanda_tangan, 
               created_at
        FROM absensi_harian 
        WHERE user_id = ?
    ");
    $stmtRiwayatHarian->execute(array($user_id));
    $riwayatHarian = $stmtRiwayatHarian->fetchAll(PDO::FETCH_ASSOC);

    // Query Apel
    $stmtRiwayatApel = $pdo->prepare("
        SELECT 'apel' AS jenis, 
               CONCAT('Apel ', UPPER(sesi)) AS judul, 
               tanggal, 
               waktu, 
               tanda_tangan, 
               created_at
        FROM presensi_apel 
        WHERE user_id = ?
    ");
    $stmtRiwayatApel->execute(array($user_id));
    $riwayatApel = $stmtRiwayatApel->fetchAll(PDO::FETCH_ASSOC);

    // Query Rapat / Acara
    $stmtRiwayatRapat = $pdo->prepare("
        SELECT 'rapat' AS jenis, 
               a.nama_acara AS judul, 
               DATE(p.waktu_absen) AS tanggal, 
               TIME(p.waktu_absen) AS waktu, 
               p.tanda_tangan, 
               p.waktu_absen AS created_at
        FROM presensi p
        JOIN acara a ON p.acara_id = a.id
        WHERE p.user_id = ?
    ");
    $stmtRiwayatRapat->execute(array($user_id));
    $riwayatRapat = $stmtRiwayatRapat->fetchAll(PDO::FETCH_ASSOC);

    // Gabungkan & urutkan descending berdasarkan created_at
    $semuaRiwayat = array_merge($riwayatHarian, $riwayatApel, $riwayatRapat);
    usort($semuaRiwayat, function ($a, $b) {
        $t1 = strtotime($b['created_at']);
        $t2 = strtotime($a['created_at']);
        if ($t1 == $t2) {
            return 0;
        }
        return ($t1 < $t2) ? -1 : 1;
    });

    // Apel bulan ini (untuk chip di beranda). Opsional ?bulan=2026-10
    $bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');
    if (!preg_match('/^\d{4}-\d{2}$/', $bulan)) { $bulan = date('Y-m'); }
    $awal  = $bulan . '-01';
    $akhir = date('Y-m-t', strtotime($awal));

    $stmtApelBulan = $pdo->prepare("
        SELECT tanggal, sesi, waktu FROM presensi_apel
        WHERE user_id = ? AND tanggal BETWEEN ? AND ?
        ORDER BY tanggal
    ");
    $stmtApelBulan->execute(array($user_id, $awal, $akhir));

    $apelBulanIni = array('bulan' => $bulan, 'pagi' => array(), 'sore' => array());
    foreach ($stmtApelBulan->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $sesi = strtolower($r['sesi']);
        if (isset($apelBulanIni[$sesi])) {
            $apelBulanIni[$sesi][] = $r['tanggal'];
        }
    }

    http_response_code(200);
    echo json_encode(array(
        'status'                  => 'success',
        'user_id'                 => (int)$user['id'],
        'nama'                    => $user['nama'],
        'jabatan'                 => $user['jabatan'],
        'username'                => $user['username'],
        'user'                    => array(
            'id'       => (int)$user['id'],
            'user_id'  => (int)$user['id'],
            'nip'      => $user['nip'],
            'nama'     => $user['nama'],
            'jabatan'  => $user['jabatan'],
            'username' => $user['username']
        ),
        'absensi_harian_hari_ini' => $absensiHarianHariIni,
        'status_hari_ini'         => $statusHariIni,
        'riwayat_terbaru'         => array_values($semuaRiwayat),
        'apel_bulan_ini'          => $apelBulanIni
    ));

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        'status'  => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ));
}
