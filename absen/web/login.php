<?php
// absen/web/login.php
// Halaman login pegawai untuk portal presensi web

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Jika sudah login, redirect ke portal
if (!empty($_SESSION['pegawai_logged_in']) && $_SESSION['pegawai_logged_in'] === true) {
    header("Location: index.php");
    exit;
}

// Ambil flash message
$errorMsg  = $_SESSION['flash_error'] ?? '';
$oldInput  = $_SESSION['flash_old'] ?? [];
unset($_SESSION['flash_error'], $_SESSION['flash_old']);

$jamDb = date('H:i');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Pegawai - Sistem Absensi Digital</title>
    <link rel="stylesheet" href="../assets/css/inter.css">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <style>
        :root {
            --primary: #0f766e;
            --primary-hover: #115e59;
        }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: linear-gradient(145deg, #042f2e 0%, #0f766e 55%, #134e4a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            color: #1f2937;
            position: relative;
        }
        /* Subtle animated background circles */
        body::before, body::after {
            content: '';
            position: fixed;
            border-radius: 50%;
            background: rgba(255,255,255,0.04);
            pointer-events: none;
        }
        body::before {
            width: 600px; height: 600px;
            top: -150px; right: -150px;
        }
        body::after {
            width: 400px; height: 400px;
            bottom: -100px; left: -100px;
        }
        .login-wrapper {
            width: 100%;
            max-width: 460px;
            position: relative;
            z-index: 1;
        }
        .login-card {
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 25px 60px -12px rgba(0,0,0,0.4);
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .login-header {
            background: linear-gradient(135deg, var(--primary) 0%, #115e59 100%);
            padding: 2.25rem 2rem 2rem;
            text-align: center;
            color: #ffffff;
            position: relative;
        }
        .login-logo {
            width: 64px;
            height: 64px;
            background: rgba(255,255,255,0.15);
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        .login-body {
            padding: 2rem 2.25rem 1.5rem;
        }
        .form-floating .form-control {
            border-radius: 12px;
            border: 1.5px solid #e2e8f0;
            padding: 1rem;
            height: 56px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }
        .form-floating .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(15, 118, 110, 0.12);
        }
        .form-floating > label {
            padding: 1rem;
            color: #94a3b8;
            font-size: 0.9rem;
        }
        .btn-login {
            background: linear-gradient(135deg, var(--primary) 0%, #115e59 100%);
            border: none;
            color: #ffffff;
            border-radius: 12px;
            padding: 0.85rem 1.5rem;
            font-weight: 700;
            font-size: 1rem;
            width: 100%;
            transition: all 0.25s ease;
            letter-spacing: 0.3px;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 20px -5px rgba(15, 118, 110, 0.4);
            color: #ffffff;
        }
        .btn-login:active {
            transform: translateY(0);
        }
        .login-footer {
            border-top: 1px solid #f1f5f9;
            padding: 1.25rem 2.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .admin-link {
            font-size: 0.8rem;
            color: #94a3b8;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.3rem 0.65rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        .admin-link:hover {
            color: #64748b;
            border-color: #cbd5e1;
            background: #f8fafc;
        }
        .divider {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #cbd5e1;
            font-size: 0.8rem;
            margin: 1.25rem 0;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #f1f5f9;
        }
        .alert-login-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            border-radius: 12px;
            padding: 0.85rem 1rem;
            font-size: 0.875rem;
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
        }
        .input-icon-wrapper {
            position: relative;
        }
        .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            font-size: 1rem;
            padding: 0;
            z-index: 5;
        }
        .toggle-password:hover {
            color: var(--primary);
        }
    </style>
</head>
<body>
<div class="login-wrapper">

    <div class="login-card">
        <div class="login-header">
            <div class="login-logo">🏛️</div>
            <h1 class="h5 fw-bold mb-1">Portal Presensi Pegawai</h1>
            <p class="mb-0 text-white-50 small">Pengadilan Agama &bull; Sistem Absensi Digital</p>
        </div>

        <div class="login-body">
            <?php if (!empty($errorMsg)): ?>
                <div class="alert-login-error">
                    <span>⚠️</span>
                    <span><?= htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>

            <p class="text-muted small mb-3 text-center">Masuk menggunakan akun kepegawaian Anda</p>

            <form action="proses_login.php" method="POST" id="form-login" autocomplete="off">
                <?= csrfField() ?>

                <div class="mb-3">
                    <div class="form-floating">
                        <input
                            type="text"
                            class="form-control"
                            id="username"
                            name="username"
                            placeholder="Username"
                            value="<?= htmlspecialchars($oldInput['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            required
                            autocomplete="username"
                            autofocus
                        >
                        <label for="username">👤 Username</label>
                    </div>
                </div>

                <div class="mb-4">
                    <div class="form-floating input-icon-wrapper">
                        <input
                            type="password"
                            class="form-control pe-5"
                            id="password"
                            name="password"
                            placeholder="Password"
                            required
                            autocomplete="current-password"
                        >
                        <label for="password">🔑 Password</label>
                        <button type="button" class="toggle-password" id="toggle-pass" aria-label="Tampilkan password">
                            👁️
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-login" id="btn-login">
                    Masuk ke Portal Presensi
                </button>
            </form>
        </div>

        <div class="login-footer">
            <span class="text-muted" style="font-size:0.78rem;">
                ⏰ <?= $jamDb ?> WIB
            </span>
            <a href="../admin/login.php" class="admin-link">
                🔒 Login sebagai Administrator
            </a>
        </div>
    </div>

</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
    // Toggle password visibility
    const toggleBtn = document.getElementById('toggle-pass');
    const passInput = document.getElementById('password');
    if (toggleBtn && passInput) {
        toggleBtn.addEventListener('click', function () {
            const isPassword = passInput.type === 'password';
            passInput.type = isPassword ? 'text' : 'password';
            toggleBtn.textContent = isPassword ? '🙈' : '👁️';
        });
    }

    // Disable button on submit to prevent double-click
    const formLogin = document.getElementById('form-login');
    const btnLogin = document.getElementById('btn-login');
    if (formLogin && btnLogin) {
        formLogin.addEventListener('submit', function () {
            btnLogin.disabled = true;
            btnLogin.textContent = '⏳ Memverifikasi...';
        });
    }
</script>
</body>
</html>
