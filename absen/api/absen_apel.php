<?php
// absen/api/absen_apel.php
// Endpoint REST API: Menerima data presensi apel (pagi/sore) dari aplikasi Android

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Hanya menerima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('status' => 'error', 'message' => 'Gunakan method POST.'));
    exit;
}

require_once __DIR__ . '/../config/database.php';

// Restriksi Jaringan Kantor (IP Whitelist)
enforceNetworkRestriction();

// Verifikasi JWT Bearer Token
$tokenPayload = verifyApiAuth($pdo, 'access');

// Baca body: JSON atau Form POST
$rawInput = file_get_contents('php://input');
$rawInput = ltrim($rawInput, "\xEF\xBB\xBF"); // Strip BOM jika ada
$input    = json_decode($rawInput, true);
if (!is_array($input) || empty($input)) {
    $input = $_POST;
}

// Petakan field:
$user_id = 0;
if (isset($input['user_id']) && $input['user_id'] !== '') {
    $user_id = (int)$input['user_id'];
} elseif (isset($input['userId']) && $input['userId'] !== '') {
    $user_id = (int)$input['userId'];
} elseif (isset($tokenPayload['user_id'])) {
    $user_id = (int)$tokenPayload['user_id'];
}

$nama = '';
if (isset($input['nama']) && $input['nama'] !== '') {
    $nama = trim($input['nama']);
} elseif (isset($input['name']) && $input['name'] !== '') {
    $nama = trim($input['name']);
} elseif (isset($tokenPayload['nama'])) {
    $nama = trim($tokenPayload['nama']);
}

$jabatan = '';
if (isset($input['jabatan']) && $input['jabatan'] !== '') {
    $jabatan = trim($input['jabatan']);
} elseif (isset($input['role']) && $input['role'] !== '') {
    $jabatan = trim($input['role']);
} elseif (isset($input['instansi']) && $input['instansi'] !== '') {
    $jabatan = trim($input['instansi']);
} elseif (isset($tokenPayload['jabatan'])) {
    $jabatan = trim($tokenPayload['jabatan']);
}

$tanda_tangan = '';
if (isset($input['tanda_tangan']) && $input['tanda_tangan'] !== '') {
    $tanda_tangan = trim($input['tanda_tangan']);
} elseif (isset($input['tandaTangan']) && $input['tandaTangan'] !== '') {
    $tanda_tangan = trim($input['tandaTangan']);
} elseif (isset($input['signature']) && $input['signature'] !== '') {
    $tanda_tangan = trim($input['signature']);
}

$sesi = '';
if (isset($input['sesi']) && $input['sesi'] !== '') {
    $sesi = strtolower(trim($input['sesi']));
} elseif (isset($input['session']) && $input['session'] !== '') {
    $sesi = strtolower(trim($input['session']));
}

$tanggal = '';
if (isset($input['tanggal']) && $input['tanggal'] !== '') {
    $tanggal = trim($input['tanggal']);
} elseif (isset($input['date']) && $input['date'] !== '') {
    $tanggal = trim($input['date']);
} else {
    $tanggal = date('Y-m-d');
}

// Waktu SELALU diambil dari server untuk mencegah manipulasi klien
$waktu = date('H:i:s');

// Jika user_id dikirim tapi nama/jabatan kosong -> auto-fetch dari DB users
if ($user_id > 0 && ($nama === '' || $jabatan === '')) {
    $stmtFetchUser = $pdo->prepare("SELECT nama, jabatan FROM users WHERE id = ? LIMIT 1");
    $stmtFetchUser->execute(array($user_id));
    $uData = $stmtFetchUser->fetch(PDO::FETCH_ASSOC);
    if ($uData) {
        if ($nama === '') $nama = $uData['nama'];
        if ($jabatan === '') $jabatan = $uData['jabatan'];
    }
}

// Validasi field kosong
$missing = array();
if ($nama === '')         $missing[] = 'nama';
if ($jabatan === '')      $missing[] = 'jabatan';
if ($sesi === '')         $missing[] = 'sesi';
if ($tanda_tangan === '') $missing[] = 'tanda_tangan';

if (!empty($missing)) {
    http_response_code(400);
    echo json_encode(array(
        'status'          => 'error',
        'message'         => 'Field kosong/tidak dikirim: ' . implode(', ', $missing),
        'missing_fields'  => $missing,
        'received_fields' => is_array($input) ? array_keys($input) : array(),
        'raw_body_length' => strlen($rawInput)
    ));
    exit;
}

// Validasi nilai sesi (hanya 'pagi' atau 'sore')
if (!in_array($sesi, array('pagi', 'sore'), true)) {
    http_response_code(400);
    echo json_encode(array(
        'status'  => 'error',
        'message' => "Nilai sesi tidak valid ('" . htmlspecialchars($sesi) . "'). Sesi harus 'pagi' atau 'sore'."
    ));
    exit;
}

// Validasi Hari dan Jam Apel
// Apel Pagi: Hanya hari Senin (1), Jam 07:00 - 08:30
// Apel Sore: Hanya hari Jumat (5), Jam 16:00 - 17:30
$dayOfWeek = (int)date('N', strtotime($tanggal)); // 1 (Senin) s/d 7 (Minggu)

if ($sesi === 'pagi') {
    if ($dayOfWeek !== 1) {
        http_response_code(400);
        echo json_encode(array(
            'status'  => 'error',
            'message' => 'Absen Apel Pagi hanya dapat dilakukan pada hari Senin.'
        ));
        exit;
    }
    if ($waktu < '07:00:00' || $waktu > '08:30:00') {
        http_response_code(400);
        echo json_encode(array(
            'status'  => 'error',
            'message' => 'Absen Apel Pagi hanya dibuka pukul 07:00 - 08:30 WIB.'
        ));
        exit;
    }
} elseif ($sesi === 'sore') {
    if ($dayOfWeek !== 5) {
        http_response_code(400);
        echo json_encode(array(
            'status'  => 'error',
            'message' => 'Absen Apel Sore hanya dapat dilakukan pada hari Jumat.'
        ));
        exit;
    }
    if ($waktu < '16:00:00' || $waktu > '17:30:00') {
        http_response_code(400);
        echo json_encode(array(
            'status'  => 'error',
            'message' => 'Absen Apel Sore hanya dibuka pukul 16:00 - 17:30 WIB.'
        ));
        exit;
    }
}

// Auto-tambah prefix jika Android kirim raw Base64
if (strpos($tanda_tangan, 'data:image/') !== 0) {
    $tanda_tangan = 'data:image/png;base64,' . str_replace(array("\r", "\n", " "), array('', '', '+'), $tanda_tangan);
}

try {
    // Cek duplikasi jika user_id dikirim
    if ($user_id > 0) {
        $stmtCek = $pdo->prepare("SELECT id FROM presensi_apel WHERE user_id = ? AND tanggal = ? AND sesi = ? LIMIT 1");
        $stmtCek->execute(array($user_id, $tanggal, $sesi));
        if ($stmtCek->fetch()) {
            http_response_code(409); // Conflict
            echo json_encode(array(
                'status'  => 'error',
                'message' => 'Anda sudah melakukan absensi apel ' . strtoupper($sesi) . ' pada tanggal ini (' . $tanggal . ').'
            ));
            exit;
        }
    }

    // Simpan presensi apel
    $stmt = $pdo->prepare("INSERT INTO presensi_apel (user_id, nama, jabatan, tanda_tangan, sesi, tanggal, waktu) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(array($user_id > 0 ? $user_id : null, $nama, $jabatan, $tanda_tangan, $sesi, $tanggal, $waktu));

    http_response_code(200);
    echo json_encode(array(
        'status'  => 'success',
        'message' => 'Absensi apel berhasil disimpan.'
    ));

} catch (\Exception $e) {
    http_response_code(500);
    // Jangan expose detail error ke klien di produksi
    error_log('[absen_apel] ' . $e->getMessage());
    echo json_encode(array(
        'status'  => 'error',
        'message' => 'Terjadi kesalahan pada server. Silakan coba lagi.'
    ));
}
