<?php
// absen/config/csrf.php
// Helper Proteksi CSRF (Cross-Site Request Forgery)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Hasilkan CSRF Token (atau ambil jika sudah ada di session)
 */
function generateCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Hasilkan HTML Hidden Input Tag untuk Form POST
 */
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verifikasi CSRF Token
 */
function verifyCsrfToken($token = null) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }

    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (empty($sessionToken) || empty($token)) {
        return false;
    }

    return hash_equals($sessionToken, $token);
}

/**
 * Enforce CSRF Check untuk request POST.
 * Menghentikan eksekusi jika token tidak valid.
 */
function enforceCsrfCheck() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCsrfToken()) {
            http_response_code(403);
            die('<!DOCTYPE html><html lang="id"><head><title>403 Forbidden - CSRF Error</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"></head><body class="bg-light d-flex align-items-center justify-content-center vh-100"><div class="card shadow p-4 text-center" style="max-width:480px;"><h3 class="text-danger">403 Forbidden</h3><p class="text-muted">Permintaan ditolak: Token CSRF tidak valid atau telah kadaluarsa. Silakan muat ulang halaman dan coba lagi.</p><a href="javascript:history.back()" class="btn btn-secondary">Kembali</a></div></body></html>');
        }
    }
}
