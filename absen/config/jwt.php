<?php
// absen/config/jwt.php
// Utility Helper untuk JWT (JSON Web Token) HS256

if (!defined('JWT_SECRET')) {
    define('JWT_SECRET', 'webabsenpa_jwt_secret_key_2026_super_secure_987654321_hash');
}

/**
 * Encode data ke format Base64Url (Safe untuk Header/URL)
 */
function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Decode data dari format Base64Url
 */
function base64UrlDecode($data) {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}

/**
 * Generate JWT Token (HS256)
 */
function generateJwtToken(array $payload, int $expiresInSeconds = 28800) {
    $header = [
        'alg' => 'HS256',
        'typ' => 'JWT'
    ];

    $now = time();
    $payload['iat'] = $now;
    $payload['exp'] = $now + $expiresInSeconds;
    if (empty($payload['jti'])) {
        $payload['jti'] = bin2hex(random_bytes(16));
    }

    $base64Header  = base64UrlEncode(json_encode($header));
    $base64Payload = base64UrlEncode(json_encode($payload));

    $signature = hash_hmac('sha256', "$base64Header.$base64Payload", JWT_SECRET, true);
    $base64Signature = base64UrlEncode($signature);

    return "$base64Header.$base64Payload.$base64Signature";
}

/**
 * Decode dan Verifikasi JWT Token (HS256)
 */
function decodeAndVerifyJwtToken(string $token, string $expectedType = null) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        throw new \Exception('Format token JWT tidak valid.');
    }

    list($base64Header, $base64Payload, $base64Signature) = $parts;

    $header = json_decode(base64UrlDecode($base64Header), true);
    if (!is_array($header) || ($header['alg'] ?? '') !== 'HS256') {
        throw new \Exception('Algoritma JWT tidak didukung.');
    }

    // Verifikasi Signature
    $expectedSignature = base64UrlEncode(hash_hmac('sha256', "$base64Header.$base64Payload", JWT_SECRET, true));
    if (!hash_equals($expectedSignature, $base64Signature)) {
        throw new \Exception('Tanda tangan (signature) token JWT tidak valid.');
    }

    $payload = json_decode(base64UrlDecode($base64Payload), true);
    if (!is_array($payload)) {
        throw new \Exception('Payload JWT tidak dapat di-parse.');
    }

    // Cek Expired
    if (isset($payload['exp']) && time() >= $payload['exp']) {
        throw new \Exception('Token JWT telah kadaluarsa.');
    }

    // Cek Tipe Token (access vs refresh) jika diminta
    if ($expectedType !== null && isset($payload['type']) && $payload['type'] !== $expectedType) {
        throw new \Exception("Tipe token tidak sesuai (diharapkan: {$expectedType}).");
    }

    return $payload;
}

/**
 * Ambil Bearer Token dari Header Request HTTP
 */
function getBearerToken() {
    $headers = null;

    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER['Authorization']);
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }

    if (!empty($headers)) {
        if (preg_match('/Bearer\s(\S+)/i', $headers, $matches)) {
            return $matches[1];
        }
    }

    return null;
}

/**
 * Blacklist sebuah token ke database
 */
function blacklistToken($pdo, string $token, int $userId = 0) {
    try {
        $payload = decodeAndVerifyJwtToken($token);
        $jti = $payload['jti'] ?? null;
        $exp = $payload['exp'] ?? (time() + 86400);
        $uId = $userId > 0 ? $userId : ($payload['user_id'] ?? 0);

        if ($jti) {
            $expiresAt = date('Y-m-d H:i:s', $exp);
            $stmt = $pdo->prepare("INSERT IGNORE INTO token_blacklist (token_jti, user_id, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$jti, $uId, $expiresAt]);
        }
        return true;
    } catch (\Exception $e) {
        return false;
    }
}

/**
 * Cek apakah token berada dalam blacklist
 */
function isTokenBlacklisted($pdo, string $jti) {
    if (empty($jti)) return false;
    try {
        $stmt = $pdo->prepare("SELECT id FROM token_blacklist WHERE token_jti = ? LIMIT 1");
        $stmt->execute([$jti]);
        return (bool)$stmt->fetch();
    } catch (\PDOException $e) {
        return false;
    }
}

/**
 * Autentikasi API: Memverifikasi Bearer Token dari Header HTTP
 * Mengembalikan array payload token jika sukses, atau menghentikan execution dengan HTTP 401/403 jika gagal.
 */
function verifyApiAuth($pdo, string $expectedType = 'access') {
    $token = getBearerToken();

    if (!$token) {
        http_response_code(401);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Akses ditolak. Header Authorization: Bearer <token> wajib dikirim.'
        ]);
        exit;
    }

    try {
        $payload = decodeAndVerifyJwtToken($token, $expectedType);

        // Cek apakah token di-blacklist
        if (isset($payload['jti']) && isTokenBlacklisted($pdo, $payload['jti'])) {
            http_response_code(401);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Token telah di-revoke (logout). Silakan login kembali.'
            ]);
            exit;
        }

        // Cek status aktif user dari DB
        if (isset($payload['user_id'])) {
            $stmtUser = $pdo->prepare("SELECT is_active FROM users WHERE id = ? LIMIT 1");
            $stmtUser->execute([$payload['user_id']]);
            $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

            if (!$user || (int)$user['is_active'] === 0) {
                http_response_code(403);
                echo json_encode([
                    'status'  => 'error',
                    'message' => 'Akun Anda tidak aktif atau telah dinonaktifkan.'
                ]);
                exit;
            }
        }

        return $payload;
    } catch (\Exception $e) {
        http_response_code(401);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Autentikasi gagal: ' . $e->getMessage()
        ]);
        exit;
    }
}
