<?php
// absen/api/logout.php
// Endpoint REST API: Logout User / Revoke JWT Bearer Token

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
    echo json_encode(['status' => 'error', 'message' => 'Gunakan method POST.']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

// Memverifikasi access token dari header Authorization: Bearer <token>
$payload = verifyApiAuth($pdo, 'access');

$token = getBearerToken();
if ($token) {
    blacklistToken($pdo, $token, (int)(isset($payload['user_id']) ? $payload['user_id'] : 0));
}

// Opsional: blacklist juga refresh_token jika dikirim di request body
$rawInput = file_get_contents('php://input');
$rawInput = ltrim($rawInput, "\xEF\xBB\xBF");
$input    = json_decode($rawInput, true);
if (is_array($input) && !empty($input['refresh_token'])) {
    blacklistToken($pdo, trim($input['refresh_token']), (int)(isset($payload['user_id']) ? $payload['user_id'] : 0));
}

http_response_code(200);
echo json_encode(array(
    'status'  => 'success',
    'message' => 'Logout berhasil. Token telah di-revoke.'
));
