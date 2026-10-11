<?php
// absen/admin/manage_users.php
require_once 'auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php?tab=users");
    exit;
}

enforceCsrfCheck();

$action = $_POST['action'] ?? '';

try {
    if ($action === 'create') {
        $nip      = trim($_POST['nip'] ?? '');
        $nama     = trim($_POST['nama'] ?? '');
        $jabatan  = trim($_POST['jabatan'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($nama === '' || $jabatan === '' || $username === '' || $password === '') {
            header("Location: dashboard.php?tab=users&msg=error_fields");
            exit;
        }

        // Validasi NIP: jika diisi, harus angka
        if ($nip !== '' && !preg_match('/^[0-9]+$/', $nip)) {
            header("Location: dashboard.php?tab=users&msg=error_nip_invalid");
            exit;
        }

        // Validasi Username: tidak boleh mengandung spasi
        if (preg_match('/\s/', $username)) {
            header("Location: dashboard.php?tab=users&msg=error_username_spaces");
            exit;
        }

        $hashPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO users (nip, nama, jabatan, username, password, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute([$nip ?: null, $nama, $jabatan, $username, $hashPassword]);
        logAdminActivity($pdo, 'Tambah Pegawai', "Pegawai: {$nama} ({$username})");

        header("Location: dashboard.php?tab=users&msg=success");
        exit;
    }

    if ($action === 'edit') {
        $id       = (int)($_POST['user_id'] ?? 0);
        $nip      = trim($_POST['nip'] ?? '');
        $nama     = trim($_POST['nama'] ?? '');
        $jabatan  = trim($_POST['jabatan'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($id <= 0 || $nama === '' || $jabatan === '' || $username === '') {
            header("Location: dashboard.php?tab=users&msg=error_fields");
            exit;
        }

        // Validasi NIP
        if ($nip !== '' && !preg_match('/^[0-9]+$/', $nip)) {
            header("Location: dashboard.php?tab=users&msg=error_nip_invalid");
            exit;
        }

        // Validasi Username
        if (preg_match('/\s/', $username)) {
            header("Location: dashboard.php?tab=users&msg=error_username_spaces");
            exit;
        }

        if ($password !== '') {
            $hashPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET nip = ?, nama = ?, jabatan = ?, username = ?, password = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$nip ?: null, $nama, $jabatan, $username, $hashPassword, $is_active, $id]);
            logAdminActivity($pdo, 'Edit & Reset Password Pegawai', "Pegawai ID: {$id} ({$nama})");
        } else {
            $stmt = $pdo->prepare("UPDATE users SET nip = ?, nama = ?, jabatan = ?, username = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$nip ?: null, $nama, $jabatan, $username, $is_active, $id]);
            logAdminActivity($pdo, 'Edit Pegawai', "Pegawai ID: {$id} ({$nama})");
        }

        header("Location: dashboard.php?tab=users&msg=success");
        exit;
    }

    if ($action === 'toggle_active') {
        $id = (int)($_POST['user_id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE users SET is_active = IF(is_active=1, 0, 1) WHERE id = ?");
            $stmt->execute([$id]);
            logAdminActivity($pdo, 'Toggle Active Status Pegawai', "Pegawai ID: {$id}");
        }
        header("Location: dashboard.php?tab=users&msg=success");
        exit;
    }

} catch (\PDOException $e) {
    if (strpos($e->getMessage(), '1062') !== false || strpos($e->getMessage(), 'Duplicate entry') !== false) {
        header("Location: dashboard.php?tab=users&msg=error_duplicate");
        exit;
    }
    header("Location: dashboard.php?tab=users&msg=error");
    exit;
}
