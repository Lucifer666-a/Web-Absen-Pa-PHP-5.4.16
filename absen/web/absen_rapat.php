<?php
// absen/web/absen_rapat.php
// Halaman form web presensi rapat/acara dinas

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$hariIndonesia = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
];
$bulanIndonesia = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

$hariIni = $hariIndonesia[date('l')];
$tgl = date('d');
$bln = $bulanIndonesia[date('m')];
$thn = date('Y');
$tanggalDisplay = "$hariIni, $tgl $bln $thn";
$jamDb = date('H:i');

// Flash messages
$errorMsg = $_SESSION['flash_error'] ?? '';
$oldInput = $_SESSION['flash_old'] ?? [];
unset($_SESSION['flash_error'], $_SESSION['flash_old']);

// Ambil daftar rapat / acara yang sedang BUKA
$daftarAcara = [];
try {
    $stmtAcara = $pdo->query("SELECT id, nama_acara FROM acara WHERE status = 'BUKA' ORDER BY id DESC");
    $daftarAcara = $stmtAcara->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $daftarAcara = [];
}

// Ambil daftar pegawai aktif untuk autocomplete opsional
$daftarPegawai = [];
try {
    $stmtUsers = $pdo->query("SELECT id, nama, jabatan FROM users WHERE is_active = 1 ORDER BY nama ASC");
    $daftarPegawai = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $daftarPegawai = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presensi Rapat / Acara - Sistem Absensi Digital</title>
    <link rel="stylesheet" href="../assets/css/inter.css">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --bg-gradient: linear-gradient(135deg, #1e3a8a 0%, #2563eb 50%, #1d4ed8 100%);
            --card-bg: rgba(255, 255, 255, 0.98);
        }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--bg-gradient);
            min-height: 100vh;
            color: #1f2937;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .form-card {
            background: var(--card-bg);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            max-width: 620px;
            width: 100%;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .form-header {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
            padding: 1.75rem 2rem;
            text-align: center;
        }
        .signature-wrapper {
            border: 2px dashed #cbd5e1;
            border-radius: 14px;
            background: #ffffff;
            position: relative;
            touch-action: none;
            overflow: hidden;
        }
        canvas#signature-pad {
            width: 100%;
            height: 180px;
            display: block;
            cursor: crosshair;
        }
        .btn-clear {
            position: absolute;
            top: 8px;
            right: 8px;
            z-index: 10;
            font-size: 0.75rem;
            padding: 0.25rem 0.65rem;
            border-radius: 8px;
        }
        .form-control, .form-select {
            border-radius: 10px;
            padding: 0.7rem 1rem;
            border: 1px solid #d1d5db;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
        }
        .btn-submit {
            background: var(--primary);
            border-color: var(--primary);
            color: #ffffff;
            border-radius: 12px;
            padding: 0.8rem 1.5rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .btn-submit:hover {
            background: var(--primary-hover);
            border-color: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.3);
            color: #ffffff;
        }
    </style>
</head>
<body>

<div class="form-card">
    <div class="form-header position-relative">
        <a href="index.php" class="btn btn-sm btn-outline-light position-absolute top-50 start-0 translate-middle-y ms-3" style="border-radius: 10px;">
            &larr; Kembali
        </a>
        <h1 class="h4 fw-bold mb-1">Presensi Rapat / Acara</h1>
        <p class="mb-0 text-white-50 small">Pengadilan Agama &bull; Tanggal: <?= $tanggalDisplay ?></p>
    </div>

    <div class="p-4 p-md-5">
        <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert">
                <div>⚠️</div>
                <div><?= htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8') ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($daftarAcara)): ?>
            <div class="text-center py-4">
                <div class="display-5 text-muted mb-3">📁</div>
                <h5 class="fw-bold text-dark">Tidak Ada Rapat Terbuka</h5>
                <p class="text-muted small">
                    Saat ini belum ada rapat atau acara dinas yang berstatus <strong>BUKA</strong>. Silakan hubungi sekretariat atau panitia rapat untuk membuka sesi rapat di sistem.
                </p>
                <a href="index.php" class="btn btn-outline-secondary mt-2">
                    Kembali ke Menu Utama
                </a>
            </div>
        <?php else: ?>
            <form action="proses_rapat.php" method="POST" id="absen-rapat-form">
                <?= csrfField() ?>
                <input type="hidden" name="user_id" id="user_id" value="<?= htmlspecialchars($oldInput['user_id'] ?? '') ?>">

                <!-- Pilih Rapat / Acara -->
                <div class="mb-3">
                    <label for="acara_id" class="form-label fw-semibold">Pilih Kegiatan / Rapat <span class="text-danger">*</span></label>
                    <select class="form-select" id="acara_id" name="acara_id" required>
                        <option value="">-- Pilih Kegiatan Rapat Aktif --</option>
                        <?php foreach ($daftarAcara as $ac): ?>
                            <option value="<?= $ac['id'] ?>" <?= (($oldInput['acara_id'] ?? '') == $ac['id']) ? 'selected' : '' ?>>
                                📌 <?= htmlspecialchars($ac['nama_acara'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Autocomplete Pegawai (Opsional) -->
                <?php if (!empty($daftarPegawai)): ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted small mb-1">Pilih Pegawai Terdaftar (Opsional)</label>
                        <select class="form-select form-select-sm" id="select_pegawai">
                            <option value="">-- Pilih untuk otomatis mengisi nama & instansi --</option>
                            <?php foreach ($daftarPegawai as $p): ?>
                                <option value="<?= $p['id'] ?>" data-nama="<?= htmlspecialchars($p['nama'], ENT_QUOTES, 'UTF-8') ?>" data-jabatan="<?= htmlspecialchars($p['jabatan'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($p['nama'], ENT_QUOTES, 'UTF-8') ?> &bull; <?= htmlspecialchars($p['jabatan'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label for="nama" class="form-label fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="nama" name="nama" required placeholder="Contoh: Ahmad Fauzi, S.H." value="<?= htmlspecialchars($oldInput['nama'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label for="jabatan" class="form-label fw-semibold">Jabatan / Instansi <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="jabatan" name="jabatan" required placeholder="Contoh: Panitera Muda Hukum" value="<?= htmlspecialchars($oldInput['jabatan'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label for="pin" class="form-label fw-semibold">PIN Rapat / Acara <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="pin" name="pin" required placeholder="Masukkan PIN yang diberikan panitia" autocomplete="off" maxlength="10">
                    <div class="form-text small text-muted">
                        🔒 Masukkan kode PIN yang ditampilkan oleh panitia di layar proyektor / ruang rapat.
                    </div>
                </div>

                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label fw-semibold mb-0">Tanda Tangan Kehadiran <span class="text-danger">*</span></label>
                        <span class="text-muted small">Torehkan di area bawah</span>
                    </div>
                    <div class="signature-wrapper">
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-clear" id="btn-clear-sig">
                            ✕ Hapus TTD
                        </button>
                        <canvas id="signature-pad"></canvas>
                    </div>
                    <input type="hidden" name="tanda_tangan" id="signature64">
                    <div class="form-text small text-muted">
                        ✍️ Gunakan sentuhan jari (smartphone/tablet) atau mouse untuk membubuhkan tanda tangan.
                    </div>
                </div>

                <button type="submit" class="btn btn-submit w-100" id="btn-submit">
                    💾 Simpan Kehadiran Rapat
                </button>
            </form>
        <?php endif; ?>
    </div>

    <div class="px-4 py-3 bg-light border-top text-center text-muted small">
        Sistem Absensi Digital &bull; Server Time: <strong><?= $jamDb ?> WIB</strong>
    </div>
</div>

<script src="../assets/js/signature_pad.umd.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        const canvas = document.getElementById('signature-pad');
        const clearBtn = document.getElementById('btn-clear-sig');
        const sigInput = document.getElementById('signature64');
        const form = document.getElementById('absen-rapat-form');
        const selectPegawai = document.getElementById('select_pegawai');
        const inputNama = document.getElementById('nama');
        const inputJabatan = document.getElementById('jabatan');
        const inputUserId = document.getElementById('user_id');

        // Autocomplete pegawai
        if (selectPegawai) {
            selectPegawai.addEventListener('change', function () {
                const opt = selectPegawai.options[selectPegawai.selectedIndex];
                if (opt && opt.value) {
                    inputNama.value = opt.getAttribute('data-nama') || '';
                    inputJabatan.value = opt.getAttribute('data-jabatan') || '';
                    inputUserId.value = opt.value;
                }
            });
        }

        // Setup Signature Pad
        let signaturePad = null;
        if (canvas) {
            function resizeCanvas() {
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                const ctx = canvas.getContext("2d");
                ctx.scale(ratio, ratio);
                if (signaturePad) {
                    signaturePad.clear();
                }
            }

            signaturePad = new SignaturePad(canvas, {
                penColor: 'rgb(15, 23, 42)',
                backgroundColor: 'rgba(255, 255, 255, 0)',
                minWidth: 1.5,
                maxWidth: 3.5
            });

            window.addEventListener('resize', resizeCanvas);
            resizeCanvas();

            clearBtn.addEventListener('click', function (e) {
                e.preventDefault();
                signaturePad.clear();
            });

            if (form) {
                form.addEventListener('submit', function (e) {
                    if (signaturePad.isEmpty()) {
                        e.preventDefault();
                        alert("Tanda tangan wajib dibubuhkan sebelum mengirim presensi!");
                        return false;
                    }
                    sigInput.value = signaturePad.toDataURL('image/png');
                    document.getElementById('btn-submit').disabled = true;
                    document.getElementById('btn-submit').innerHTML = '⏳ Menyimpan...';
                });
            }
        }
    });
</script>
</body>
</html>
