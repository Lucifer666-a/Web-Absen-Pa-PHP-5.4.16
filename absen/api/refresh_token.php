<?php
// absen/api/refresh_token.php
// Endpoint REST API: Renew JWT Access Token menggunakan Refresh Token

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('status' => 'error', 'message' => 'Gunakan method POST.'));
    exit;
}

require_once __DIR__ . '/../config/database.php';

$rawInput = file_get_contents('php://input');
$rawInput = ltrim($rawInput, "\xEF\xBB\xBF");
$input    = json_decode($rawInput, true);
if (!is_array($input) || empty($input)) {
    $input = $_POST;
}

$bearerToken = getBearerToken();
$refreshToken = '';
if (isset($input['refresh_token']) && $input['refresh_token'] !== '') {
    $refreshToken = trim($input['refresh_token']);
} elseif (isset($input['refreshToken']) && $input['refreshToken'] !== '') {
    $refreshToken = trim($input['refreshToken']);
} elseif ($bearerToken) {
    $refreshToken = trim($bearerToken);
}

if (empty($refreshToken)) {
    http_response_code(400);
    echo json_encode(array(
        'status'  => 'error',
        'message' => 'Parameter refresh_token wajib dikirim (via body JSON atau Header Authorization).'
    ));
    exit;
}

try {
    // Verifikasi refresh token
    $payload = decodeAndVerifyJwtToken($refreshToken, 'refresh');

    // Cek apakah refresh token di-blacklist
    if (isset($payload['jti']) && isTokenBlacklisted($pdo, $payload['jti'])) {
        http_response_code(401);
        echo json_encode(array(
            'status'  => 'error',
            'message' => 'Refresh token telah di-revoke. Silakan login kembali.'
        ));
        exit;
    }

    $userId = isset($payload['user_id']) ? (int)$payload['user_id'] : 0;
    if ($userId <= 0) {
        http_response_code(401);
        echo json_encode(array(
            'status'  => 'error',
            'message' => 'Payload refresh token tidak valid.'
        ));
        exit;
    }

    // Ambil data user terbaru dari DB
    $stmtUser = $pdo->prepare("SELECT id, nip, nama, jabatan, username, is_active FROM users WHERE id = ? LIMIT 1");
    $stmtUser->execute(array($userId));
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user || (int)$user['is_active'] === 0) {
        http_response_code(403);
        echo json_encode(array(
            'status'  => 'error',
            'message' => 'Akun Anda tidak aktif atau telah dinonaktifkan.'
        ));
        exit;
    }

    // Blacklist refresh token lama agar tidak bisa dipakai ulang
    blacklistToken($pdo, $refreshToken, $userId);

    // Generate JWT Tokens baru
    $tokenPayload = array(
        'user_id'  => (int)$user['id'],
        'username' => $user['username'],
        'nama'     => $user['nama'],
        'jabatan'  => $user['jabatan'],
        'nip'      => $user['nip'],
        'type'     => 'access'
    );
    $newAccessToken = generateJwtToken($tokenPayload, 28800); // 8 Jam

    $newRefreshPayload = array(
        'user_id'  => (int)$user['id'],
        'username' => $user['username'],
        'type'     => 'refresh'
    );
    $newRefreshToken = generateJwtToken($newRefreshPayload, 604800); // 7 Hari

    http_response_code(200);
    echo json_encode(array(
        'status'        => 'success',
        'message'       => 'Token berhasil diperbarui.',
        'token_type'    => 'Bearer',
        'access_token'  => $newAccessToken,
        'refresh_token' => $newRefreshToken,
        'expires_in'    => 28800
    ));

} catch (\Exception $e) {
    http_response_code(401);
    echo json_encode(array(
        'status'  => 'error',
        'message' => 'Gagal memperbarui token: ' . $e->getMessage()
    ));
}
