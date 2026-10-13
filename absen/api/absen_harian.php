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
            SELECT id, tanggal, waktu_checkin, waktu_checkout, foto_path, created_at 
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

// Helper upload foto absensi harian
function handleFotoUpload($userId, $tanggal) {
    // 1. Cek dari $_FILES
    $fileInfo = $_FILES['foto'] ?? $_FILES['foto_absen'] ?? $_FILES['image'] ?? null;
    $tempFile = null;
    $originalName = '';
    $fileSize = 0;

    if ($fileInfo && isset($fileInfo['error']) && $fileInfo['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($fileInfo['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'status'  => 'error',
                'message' => 'Gagal mengunggah foto (kode error: ' . $fileInfo['error'] . ').'
            ]);
            exit;
        }
        $tempFile     = $fileInfo['tmp_name'];
        $originalName = $fileInfo['name'];
        $fileSize     = (int)$fileInfo['size'];
    } else {
        // 2. Cek base64 string jika dikirim via JSON/POST
        $base64Data = getParam('foto', getParam('foto_absen', getParam('image', null)));
        if (!empty($base64Data) && is_string($base64Data)) {
            // Strip data URI prefix jika ada (misal data:image/jpeg;base64,...)
            if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $typeMatch)) {
                $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
            }
            $decoded = base64_decode($base64Data, true);
            if ($decoded !== false && strlen($decoded) > 0) {
                $tmpHandle = tmpfile();
                if ($tmpHandle) {
                    fwrite($tmpHandle, $decoded);
                    $meta = stream_get_meta_data($tmpHandle);
                    $tempFile = $meta['uri'];
                    $fileSize = strlen($decoded);
                    $originalName = 'upload.jpg';
                }
            }
        }
    }

    if (!$tempFile || !file_exists($tempFile)) {
        return null; // Foto bersifat opsional / tidak dikirim
    }

    // Validasi ukuran maks 2MB (2 * 1024 * 1024 byte = 2,097,152 byte)
    $maxSize = 2 * 1024 * 1024;
    if ($fileSize > $maxSize) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'status'  => 'error',
            'message' => 'Ukuran file foto melebihi batas maksimal 2MB.'
        ]);
        exit;
    }

    // Validasi ekstensi
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png'];
    if (!in_array($ext, $allowedExts, true)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'status'  => 'error',
            'message' => 'Format file tidak didukung. Hanya file .jpg, .jpeg, dan .png yang diizinkan.'
        ]);
        exit;
    }

    // Validasi MIME type & signature gambar (cegah file non-gambar/malicious)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $tempFile);
    finfo_close($finfo);

    $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png'];
    if (!in_array($mimeType, $allowedMimes, true)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'status'  => 'error',
            'message' => 'File bukan gambar valid. Hanya file JPEG dan PNG yang diizinkan.'
        ]);
        exit;
    }

    // Cek dengan getimagesize untuk memastikan keabsahan gambar
    $imgSize = @getimagesize($tempFile);
    if ($imgSize === false) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'status'  => 'error',
            'message' => 'File gambar rusak atau tidak valid.'
        ]);
        exit;
    }

    // Struktur folder: absen/uploads/foto_absen/YYYY/MM/DD/
    $tglParts = explode('-', $tanggal); // [YYYY, MM, DD]
    $yyyy = $tglParts[0] ?? date('Y');
    $mm   = $tglParts[1] ?? date('m');
    $dd   = $tglParts[2] ?? date('d');

    $targetSubDir = "uploads/foto_absen/{$yyyy}/{$mm}/{$dd}";
    $targetDir    = __DIR__ . "/../{$targetSubDir}";

    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'status'  => 'error',
                'message' => 'Gagal membuat direktori penyimpanan foto di server.'
            ]);
            exit;
        }
    }

    // Nama file: {user_id}_{timestamp}.jpg (selalu ekstensi standar .jpg atau sesuai format)
    $timestamp = time();
    $targetFileName = "{$userId}_{$timestamp}.jpg";
    $targetFilePath = "{$targetDir}/{$targetFileName}";

    // Simpan file (mendukung uploaded file atau temp file)
    $saved = false;
    if (is_uploaded_file($tempFile)) {
        $saved = move_uploaded_file($tempFile, $targetFilePath);
    } else {
        $saved = copy($tempFile, $targetFilePath);
    }

    if (!$saved) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'status'  => 'error',
            'message' => 'Gagal menyimpan file foto ke server.'
        ]);
        exit;
    }

    // Return relative path dari root folder /absen/
    return "{$targetSubDir}/{$targetFileName}";
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

    // Proses upload foto jika ada
    $fotoPath = handleFotoUpload($user_id, $today);

    if ($existing) {
        if ($fotoPath !== null) {
            $stmtUpdate = $pdo->prepare("UPDATE absensi_harian SET waktu_checkin = ?, foto_path = ? WHERE id = ?");
            $stmtUpdate->execute([$currentTime, $fotoPath, $existing['id']]);
        } else {
            $stmtUpdate = $pdo->prepare("UPDATE absensi_harian SET waktu_checkin = ? WHERE id = ?");
            $stmtUpdate->execute([$currentTime, $existing['id']]);
        }
    } else {
        $stmtInsert = $pdo->prepare("INSERT INTO absensi_harian (user_id, tanggal, waktu_checkin, foto_path) VALUES (?, ?, ?, ?)");
        $stmtInsert->execute([$user_id, $today, $currentTime, $fotoPath]);
    }

    $responseData = [
        'user_id'       => $user_id,
        'tanggal'       => $today,
        'waktu'         => $currentTime,
        'waktu_checkin' => $currentTime
    ];
    if ($fotoPath !== null) {
        $responseData['foto_path'] = $fotoPath;
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'status'  => 'success',
        'message' => 'Check-in berhasil dicatat.',
        'data'    => $responseData
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

        // Proses upload foto jika dikirim saat checkout (misal jika belum ada atau diperbarui)
        $fotoPath = handleFotoUpload($user_id, $today);

        if ($fotoPath !== null) {
            $stmtUpdate = $pdo->prepare("UPDATE absensi_harian SET waktu_checkout = ?, foto_path = ? WHERE id = ?");
            $stmtUpdate->execute([$currentTime, $fotoPath, $existing['id']]);
        } else {
            $stmtUpdate = $pdo->prepare("UPDATE absensi_harian SET waktu_checkout = ? WHERE id = ?");
            $stmtUpdate->execute([$currentTime, $existing['id']]);
        }

        $responseData = [
            'user_id'        => $user_id,
            'tanggal'        => $today,
            'waktu'          => $currentTime,
            'waktu_checkout' => $currentTime,
        ];
        if ($fotoPath !== null) {
            $responseData['foto_path'] = $fotoPath;
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'status'  => 'success',
            'message' => 'Check-out berhasil dicatat.',
            'data'    => $responseData
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
