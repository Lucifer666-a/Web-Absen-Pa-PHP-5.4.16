<?php
// absen/api/absen_harian.php
// Endpoint REST API: Absensi Harian (Check-in Pagi & Check-out Sore) untuk aplikasi Android

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

// Restriksi Jaringan Kantor (IP Whitelist)
enforceNetworkRestriction();

// Verifikasi JWT Bearer Token
$tokenPayload = verifyApiAuth($pdo, 'access');

// Ambil input JSON jika ada
$rawInput = file_get_contents('php://input');
$rawInput = ltrim($rawInput, "\xEF\xBB\xBF");
$input    = json_decode($rawInput, true) ?? [];

// Helper pendukung membaca parameter dari GET / POST / JSON
function getParam($key, $default = null) {
    global $input;
    if (isset($_GET[$key])) return $_GET[$key];
    if (isset($_POST[$key])) return $_POST[$key];
    if (isset($input[$key])) return $input[$key];
    return $default;
}

$user_id = (int)getParam('user_id', getParam('userId', 0));
if ($user_id <= 0 && isset($tokenPayload['user_id'])) {
    $user_id = (int)$tokenPayload['user_id'];
}
$aksi    = strtolower(trim((string)getParam('aksi', getParam('action', ''))));

if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'status'  => 'error',
        'message' => 'Parameter user_id tidak valid.'
    ]);
    exit;
}

$today       = date('Y-m-d');
$currentTime = date('H:i:s');

try {
    // Pastikan user terdaftar
    $stmtUser = $pdo->prepare("SELECT id, nama, jabatan FROM users WHERE id = ? LIMIT 1");
    $stmtUser->execute([$user_id]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'status'  => 'error',
            'message' => 'User tidak ditemukan.'
        ]);
        exit;
    }

    // METHOD GET: Ambil riwayat absensi harian
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($aksi)) {
        $bulan = (string)getParam('bulan', date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $bulan)) {
            $bulan = date('Y-m');
        }

        $stmtRiwayat = $pdo->prepare("
            SELECT id, tanggal, waktu_checkin, waktu_checkout, created_at 
            FROM absensi_harian 
            WHERE user_id = ? AND tanggal LIKE ? 
            ORDER BY tanggal DESC
        ");
        $stmtRiwayat->execute([$user_id, $bulan . '-%']);
        $riwayat = $stmtRiwayat->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'status'  => 'success',
            'user_id' => $user_id,
            'bulan'   => $bulan,
            'data'    => $riwayat
        ]);
        exit;
    }

    // METHOD POST (atau GET dengan param aksi): Prosedur Check-in / Check-out
if ($aksi === 'checkin') {
    // Validasi: belum dibuka
    if ($currentTime < '07:00:00') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'status'  => 'error',
            'message' => 'Absensi check-in belum dibuka. Mulai jam 07:00.'
        ]);
        exit;
    }

    // Validasi: sudah ditutup (otomatis ditolak, tidak ada status terlambat)
    if ($currentTime > '08:10:00') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'status'  => 'error',
            'message' => 'Waktu check-in sudah ditutup (batas jam 08:10).'
        ]);
        exit;
    }

    // Cek apakah sudah pernah check-in hari ini
    $stmtCek = $pdo->prepare("SELECT * FROM absensi_harian WHERE user_id = ? AND tanggal = ? LIMIT 1");
    $stmtCek->execute([$user_id, $today]);
    $existing = $stmtCek->fetch(PDO::FETCH_ASSOC);

    if ($existing && !empty($existing['waktu_checkin'])) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'status'  => 'error',
            'message' => 'Anda sudah melakukan check-in hari ini.'
        ]);
        exit;
    }

    if ($existing) {
        $stmtUpdate = $pdo->prepare("UPDATE absensi_harian SET waktu_checkin = ? WHERE id = ?");
        $stmtUpdate->execute([$currentTime, $existing['id']]);
    } else {
        $stmtInsert = $pdo->prepare("INSERT INTO absensi_harian (user_id, tanggal, waktu_checkin) VALUES (?, ?, ?)");
        $stmtInsert->execute([$user_id, $today, $currentTime]);
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'status'  => 'success',
        'message' => 'Check-in berhasil dicatat.',
        'data'    => [
            'user_id'       => $user_id,
            'tanggal'       => $today,
            'waktu'         => $currentTime,
            'waktu_checkin' => $currentTime
        ]
    ]);
    exit;
    }

    if ($aksi === 'checkout') {
        // Validation: Jam < 16:00
        if ($currentTime < '16:00:00') {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'status'  => 'error',
                'message' => 'Check-out belum tersedia. Mulai jam 16:00.'
            ]);
            exit;
        }

        // Validation: Jam > 17:00
            if ($currentTime > '17:30:00') {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'status'  => 'error',
                    'message' => 'Waktu check-out sudah lewat (tutup jam 17:30).'
                ]);
                exit;
            }

        $stmtCek = $pdo->prepare("SELECT * FROM absensi_harian WHERE user_id = ? AND tanggal = ? LIMIT 1");
        $stmtCek->execute([$user_id, $today]);
        $existing = $stmtCek->fetch(PDO::FETCH_ASSOC);

        if (!$existing || empty($existing['waktu_checkin'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'status'  => 'error',
                'message' => 'Anda belum melakukan check-in hari ini.'
            ]);
            exit;
        }

        if (!empty($existing['waktu_checkout'])) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'status'  => 'error',
                'message' => 'Anda sudah melakukan check-out hari ini.'
            ]);
            exit;
        }

        $stmtUpdate = $pdo->prepare("UPDATE absensi_harian SET waktu_checkout = ? WHERE id = ?");
        $stmtUpdate->execute([$currentTime, $existing['id']]);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'status'  => 'success',
            'message' => 'Check-out berhasil dicatat.',
            'data'    => [
                'user_id'        => $user_id,
                'tanggal'        => $today,
                'waktu'          => $currentTime,
                'waktu_checkout' => $currentTime,
            ]
        ]);
        exit;
    }

    // Aksi tidak valid
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'status'  => 'error',
        'message' => 'Aksi tidak valid. Gunakan aksi "checkin" atau "checkout".'
    ]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'status'  => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
