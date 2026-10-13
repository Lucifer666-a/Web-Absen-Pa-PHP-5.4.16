CREATE DATABASE IF NOT EXISTS `db_absen`;
USE `db_absen`;

-- 1. Tabel Pengaturan (Hanya untuk token statis dan konfigurasi global)
CREATE TABLE IF NOT EXISTS `pengaturan` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `access_token` VARCHAR(64) NOT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Tabel Admin
CREATE TABLE IF NOT EXISTS `admin` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `nama_lengkap` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabel Users (Pengguna/Pegawai Android)
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nip` VARCHAR(50) NULL UNIQUE,
  `nama` VARCHAR(150) NOT NULL,
  `jabatan` VARCHAR(100) NOT NULL,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Tabel Acara (Penyimpanan Multi-Acara)
CREATE TABLE IF NOT EXISTS `acara` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nama_acara` VARCHAR(150) NOT NULL,
  `pin_acara` VARCHAR(10) NOT NULL,
  `status` ENUM('BUKA', 'TUTUP') DEFAULT 'BUKA',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Tabel Presensi (Diubah menjadi relasional ke Acara dan Users)
CREATE TABLE IF NOT EXISTS `presensi` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `acara_id` INT NOT NULL,
  `user_id` INT NULL,
  `nama` VARCHAR(100) NOT NULL,
  `jabatan` VARCHAR(100) NOT NULL,
  `tanda_tangan` MEDIUMTEXT NOT NULL,
  `waktu_absen` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_presensi_acara` FOREIGN KEY (`acara_id`) REFERENCES `acara` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_presensi_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Tabel Presensi Apel (Apel Senin Pagi & Jumat Sore)
CREATE TABLE IF NOT EXISTS `presensi_apel` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NULL,
  `nama` VARCHAR(150) NOT NULL,
  `jabatan` VARCHAR(100) NOT NULL,
  `tanda_tangan` MEDIUMTEXT NULL,
  `sesi` ENUM('pagi', 'sore') NOT NULL,
  `tanggal` DATE NOT NULL,
  `waktu` TIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_apel_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- 7. Tabel Absensi Harian (Check-in Pagi & Check-out Sore)
CREATE TABLE IF NOT EXISTS `absensi_harian` (
  `id`              INT NOT NULL AUTO_INCREMENT,
  `user_id`         INT NOT NULL,
  `tanggal`         DATE NOT NULL,
  `waktu_checkin`   TIME NULL,
  `waktu_checkout`  TIME NULL,
  `status_checkin`  ENUM('tepat_waktu', 'terlambat') NULL,
  `foto_path`       VARCHAR(255) NULL,
  `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_tanggal` (`user_id`, `tanggal`),
  CONSTRAINT `fk_harian_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Data Awal Pengaturan (Token URL Statis: TOKENSTATISANDROID)
INSERT INTO `pengaturan` (`access_token`) 
VALUES ('TOKENSTATISANDROID');

-- Data Awal Admin (username: admin, password: admin123)
INSERT INTO `admin` (`username`, `password`, `nama_lengkap`) 
VALUES ('admin', '$2y$10$O9Jb84PS8ct56HafnNo2o.ccL9/FCC2LFkJgQi7oJFjoA/OOfv.DO', 'Administrator Utama');

-- Data Awal User Sample (username: pegawai1, password: user123)
INSERT INTO `users` (`nip`, `nama`, `jabatan`, `username`, `password`) 
VALUES ('199001012020121001', 'Ahmad Fauzi', 'Staf IT', 'pegawai1', '$2y$10$O9Jb84PS8ct56HafnNo2o.ccL9/FCC2LFkJgQi7oJFjoA/OOfv.DO');

-- Data Awal Acara Default
INSERT INTO `acara` (`nama_acara`, `pin_acara`, `status`) 
VALUES ('Rapat Perdana Acara Default', '123456', 'BUKA');

