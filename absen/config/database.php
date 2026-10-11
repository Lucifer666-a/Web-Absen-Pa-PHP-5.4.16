<?php
// absen/config/database.php

date_default_timezone_set('Asia/Jakarta');

$host = '127.0.0.1';
$db   = 'db_absen';
$user = 'root';
$pass = ''; // Sesuaikan dengan password database local Anda
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    // Otomatis pastikan tabel users tersedia
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `nip` VARCHAR(50) NULL UNIQUE,
        `nama` VARCHAR(150) NOT NULL,
        `jabatan` VARCHAR(100) NOT NULL,
        `username` VARCHAR(50) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Pastikan kolom is_active ada jika tabel sudah terbuat sebelumnya
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `password`");
    } catch (\PDOException $ex) {
        // Kolom sudah ada
    }

    // Otomatis pastikan tabel presensi_apel tersedia
    $pdo->exec("CREATE TABLE IF NOT EXISTS `presensi_apel` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `user_id` INT NULL,
        `nama` VARCHAR(150) NOT NULL,
        `jabatan` VARCHAR(100) NOT NULL,
        `tanda_tangan` MEDIUMTEXT NULL,
        `sesi` ENUM('pagi', 'sore') NOT NULL,
        `tanggal` DATE NOT NULL,
        `waktu` TIME NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Otomatis pastikan tabel absensi_harian tersedia
    $pdo->exec("CREATE TABLE IF NOT EXISTS `absensi_harian` (
        `id`              INT NOT NULL AUTO_INCREMENT,
        `user_id`         INT NOT NULL,
        `tanggal`         DATE NOT NULL,
        `waktu_checkin`   TIME NULL,
        `waktu_checkout`  TIME NULL,
        `status_checkin`  ENUM('tepat_waktu', 'terlambat') NULL,
        `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_user_tanggal` (`user_id`, `tanggal`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Pastikan kolom user_id ada pada presensi_apel jika tabel sudah terbuat sebelumnya
    try {
        $pdo->exec("ALTER TABLE `presensi_apel` ADD COLUMN `user_id` INT NULL AFTER `id`");
    } catch (\PDOException $ex) {
        // Kolom sudah ada
    }

    // Pastikan kolom user_id ada pada presensi jika tabel sudah terbuat sebelumnya
    try {
        $pdo->exec("ALTER TABLE `presensi` ADD COLUMN `user_id` INT NULL AFTER `acara_id`");
    } catch (\PDOException $ex) {
        // Kolom sudah ada
    }

    // Otomatis pastikan tabel pin_attempts tersedia (Rate Limiting / Lockout PIN)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `pin_attempts` (
        `id`         INT NOT NULL AUTO_INCREMENT,
        `ip_address` VARCHAR(45) NOT NULL,
        `attempts`   INT NOT NULL DEFAULT 1,
        `last_attempt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_ip` (`ip_address`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Otomatis pastikan tabel log_aktivitas tersedia
    $pdo->exec("CREATE TABLE IF NOT EXISTS `log_aktivitas` (
        `id`         INT NOT NULL AUTO_INCREMENT,
        `admin_id`   INT NULL,
        `admin_nama` VARCHAR(100) NOT NULL,
        `aksi`       VARCHAR(255) NOT NULL,
        `detail`     TEXT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Otomatis pastikan tabel token_blacklist tersedia (JWT Revocation / Logout)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `token_blacklist` (
        `id`         INT NOT NULL AUTO_INCREMENT,
        `token_jti`  VARCHAR(128) NOT NULL,
        `user_id`    INT NOT NULL,
        `expires_at` TIMESTAMP NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_jti` (`token_jti`),
        KEY `idx_expires` (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

} catch (\PDOException $e) {
    die("Koneksi Database Gagal: " . $e->getMessage());
}

require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/ip_whitelist.php';

/**
 * Helper: Record Admin Activity Log
 */
function logAdminActivity($pdo, $aksi, $detail = '') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $admin_id   = $_SESSION['admin_id'] ?? null;
    $admin_nama = $_SESSION['admin_nama'] ?? 'System/Admin';

    try {
        $stmt = $pdo->prepare("INSERT INTO log_aktivitas (admin_id, admin_nama, aksi, detail) VALUES (?, ?, ?, ?)");
        $stmt->execute([$admin_id, $admin_nama, $aksi, $detail]);
    } catch (\PDOException $ex) {
        // Ignore if logging fails
    }
}

/**
 * Helper: Validasi sesi admin sudah login
 */
function checkAdminSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header("Location: ../admin/login.php");
        exit;
    }
}
?>
